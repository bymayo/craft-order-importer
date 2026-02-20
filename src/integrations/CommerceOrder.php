<?php

namespace bymayo\craftorderimporter\integrations;

use bymayo\craftorderimporter\Plugin as OrderImporter;
use bymayo\craftorderimporter\elements\CommerceOrder as CommerceOrderElement;

use Craft;
use craft\base\ElementInterface;
use craft\db\Query;

use craft\commerce\elements;
use craft\commerce\elements\Order;
use craft\commerce\models\LineItem;
use craft\commerce\Plugin as Commerce;
use craft\commerce\records\Transaction as TransactionRecord;
use craft\elements\User;

use craft\feedme\base\Element;
use craft\feedme\events\FeedProcessEvent;
use craft\feedme\helpers\DataHelper;
use craft\feedme\helpers\DateHelper;
use craft\feedme\Plugin;
use craft\feedme\services\Process;

use yii\base\Event;

use Cake\Utility\Hash;
use Carbon\Carbon;

use DateTime;
use Exception;

/**
 *
 * @property-read string $mappingTemplate
 * @property-read mixed $groups
 * @property-write mixed $model
 * @property-read string $groupsTemplate
 * @property-read string $columnTemplate
 */
class CommerceOrder extends Element
{
    // Properties
    // =========================================================================

    private const DATE_FORMAT = "Y-m-d\\TH:i:s";

    private array $_gatewayCache = [];
    private array $_orderStatusCache = [];

    /**
     * @var string
     */
    public static string $name = 'Commerce Orders';

    /**
     * @var string
     */
    public static string $class = CommerceOrderElement::class;

    // Templates
    // =========================================================================

    /**
     * @inheritDoc
     */
    public function getGroupsTemplate(): string
    {
        return 'order-importer/groups';
    }

    /**
     * @inheritDoc
     */
    public function getColumnTemplate(): string
    {

        return 'order-importer/column';
    }

    /**
     * @inheritDoc
     */
    public function getMappingTemplate(): string
    {
        return 'order-importer/map';
    }
    // Public Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    public function init(): void
    {
        parent::init();

        Event::on(Process::class, Process::EVENT_STEP_BEFORE_PARSE_CONTENT, function(FeedProcessEvent $event) {

            $feed = $event->feed;
            $fieldMapping = $feed['fieldMapping'];

            if (!$event->element->id) {
                $newOrderElement = Craft::createObject(\craft\commerce\elements\Order::class);
                $originalScenario = $event->element->getScenario();
                $event->element->setScenario(\craft\base\Element::SCENARIO_ESSENTIALS);
                if (!Craft::$app->getDrafts()->saveElementAsDraft($newOrderElement, null, null, null, false)) {
                    throw new Exception('Unable to create order element as unsaved');
                }
                $event->element->setScenario($originalScenario);
            }

            return $event;

        });

        Event::on(Process::class, Process::EVENT_STEP_BEFORE_ELEMENT_SAVE, function(FeedProcessEvent $event) {

            $this->_parseBillingAddress($event);
            $this->_parseShippingAddress($event);

        });

        Event::on(Process::class, Process::EVENT_STEP_AFTER_ELEMENT_SAVE, function(FeedProcessEvent $event) {

            // Parse customer first (updates DB directly)
            $this->_parseCustomer($event);

            // Fetch order once after customer is set, pass to remaining methods
            $order = Commerce::getInstance()->getOrders()->getOrderById($event->element->id);

            if (!$order) {
                OrderImporter::warn('Order not found after save for element ' . $event->element->id);
                return;
            }

            $this->_parseLineItems($event, $order);
            $this->_parseAdjustments($event, $order);

            // Re-fetch order so transaction totals reflect saved line items/adjustments
            $freshOrder = Commerce::getInstance()->getOrders()->getOrderById($event->element->id);
            $this->_parseTransactions($event, $freshOrder ?? $order); // Should come after all costs
            $this->_parseShippingMethod($event); // Should always come last

        });

    }

    
    private function _parseAdjustments($event, Order $order): void
    {

        $adjustments = [
            [
                'amountField' => 'shipping-total',
                'nameField' => 'shipping-methodName',
                'type' => 'shipping',
            ],
            [
                'amountField' => 'tax-total',
                'nameField' => 'tax-rateName',
                'descriptionField' => 'tax-rate',
                'type' => 'tax',
            ],
            [
                'amountField' => 'discount-total',
                'nameField' => 'discount-name',
                'descriptionField' => 'discount-description',
                'type' => 'discount',
            ]
        ];

        foreach ($adjustments as $adjustment) {

            $feed = $event->feed;

            $amountField = $adjustment['amountField'];

            if (isset($feed['fieldMapping'][$amountField])) {

                $amountFieldInfo = $feed['fieldMapping'][$amountField];
                $amountValue = $this->fetchSimpleValue($event->feedData, $amountFieldInfo);

                $nameField = $adjustment['nameField'];

                if (!isset($feed['fieldMapping'][$nameField])) {
                    OrderImporter::warn('Missing name field mapping for ' . $adjustment['type'] . ' adjustment on order ' . $order->id);
                    continue;
                }

                $nameFieldInfo = $feed['fieldMapping'][$nameField];
                $nameValue = $this->fetchSimpleValue($event->feedData, $nameFieldInfo);

                $descriptionValue = null;
                if (isset($adjustment['descriptionField']) && isset($feed['fieldMapping'][$adjustment['descriptionField']])) {
                    $descriptionFieldInfo = $feed['fieldMapping'][$adjustment['descriptionField']];
                    $descriptionValue = $this->fetchSimpleValue($event->feedData, $descriptionFieldInfo);
                }

                $params = [
                    'orderId' => $order->id,
                    'type' => $adjustment['type'],
                    'name' => $nameValue,
                    'amount' => $amountValue,
                    'sourceSnapshot' => json_encode([])
                ];

                if ($descriptionValue !== null) {
                    $params['description'] = $descriptionValue;
                }

                if (!(new \craft\db\Query())
                    ->createCommand()
                    ->insert('{{%commerce_orderadjustments}}', $params)
                    ->execute()) {
                    OrderImporter::warn('Failed to insert ' . $adjustment['type'] . ' adjustment for order ' . $order->id);
                }

            }

        }

    }

    private function _parseShippingMethod($event): void
    {

        // @TODO: Parse shipping method

        // $feed = $event->feed;
        // $fieldHandle = 'shipping-methodName';
        // $fieldInfo = $feed['fieldMapping'][$fieldHandle];
        // $value = $this->fetchSimpleValue($event->feedData, $fieldInfo);

        // $event->element->shippingMethodHandle = $value;
        
        // Craft::$app->getElements()->saveElement($event->element, false);

    }

    private function _parseLineItems($event, Order $order): void
    {

        $feed = $event->feed;

        $lineItems = array();

        foreach ($feed['fieldMapping'] as $fieldHandle => $fieldInfo) {

            if (str_contains($fieldHandle, 'lineItems-')) {

                $attribute = str_replace('lineItems-', '', $fieldHandle);
                $attributeValue = DataHelper::fetchArrayValue($event->feedData, $fieldInfo);

                $totalLineItems = count($attributeValue);

                for ($i = 0; $i < $totalLineItems; $i++) {
                    $lineItems[$i][$attribute] = $attributeValue[$i] ?? null;
                    $lineItems[$i]['orderId'] = $order->id;
                }
            }

        }

        foreach ($lineItems as $lineItemData) {

            if (empty($lineItemData['purchasableId'])) {
                OrderImporter::warn('Skipping line item with missing purchasableId for order ' . $order->id);
                continue;
            }

            $lineItem = Commerce::getInstance()->getLineItems()->create(
                $order,
                [
                    'purchasableId' => $lineItemData['purchasableId'],
                    'options' => $lineItemData['options'] ?? [],
                    'qty' => max(1, (int) ($lineItemData['qty'] ?? 1)),
                    'note' => $lineItemData['note'] ?? ''
                ]
            );

            $lineItem->setOrder($order);

            Commerce::getInstance()->getLineItems()->saveLineItem($lineItem, false);

        }

    }

    private function _parseBillingAddress($event): void
    {

        $feed = $event->feed;
        $billingAddress = [];

        foreach ($feed['fieldMapping'] as $fieldHandle => $fieldInfo) {
            if (str_contains($fieldHandle, 'billingAddress-')) {
                $attribute = str_replace('billingAddress-', '', $fieldHandle);
                $billingAddress[$attribute] = DataHelper::fetchSimpleValue($event->feedData, $fieldInfo);
            }
        }

        if (!empty($billingAddress)) {
            $event->element->billingAddress = $billingAddress;
        }

    }

    private function _parseShippingAddress($event): void
    {

        $feed = $event->feed;
        $shippingAddress = [];

        foreach ($feed['fieldMapping'] as $fieldHandle => $fieldInfo) {
            if (str_contains($fieldHandle, 'shippingAddress-')) {
                $attribute = str_replace('shippingAddress-', '', $fieldHandle);
                $shippingAddress[$attribute] = DataHelper::fetchSimpleValue($event->feedData, $fieldInfo);
            }
        }

        if (!empty($shippingAddress)) {
            $event->element->shippingAddress = $shippingAddress;
        }

    }

    private function _parseCustomer($event): void
    {

        $feed = $event->feed;
        $element = $event->element;

        // Get email directly from feed data
        $email = null;
        if (isset($feed['fieldMapping']['email'])) {
            $emailFieldInfo = $feed['fieldMapping']['email'];
            $email = $this->fetchSimpleValue($event->feedData, $emailFieldInfo);
        }

        if (!$email) {
            OrderImporter::log('_parseCustomer: No email found in feed data for order ' . $element->id);
            return;
        }

        // Find existing user by email, or create a new one
        $user = User::find()->email($email)->one();

        if (!$user) {
            $user = new User();
            $user->email = $email;
            $user->username = $email;

            try {
                if (!Craft::$app->getElements()->saveElement($user, false)) {
                    OrderImporter::warn('Unable to create customer for order ' . $element->id);
                    return;
                }
            } catch (\Throwable $e) {
                // Race condition: another process may have created the user concurrently
                $user = User::find()->email($email)->one();
                if (!$user) {
                    OrderImporter::warn('Unable to create or find customer for order ' . $element->id);
                    return;
                }
            }

            OrderImporter::log('Created customer for order ' . $element->id);
        }

        // Update customerId directly in the database since the order is already saved
        Craft::$app->getDb()->createCommand()
            ->update('{{%commerce_orders}}', ['customerId' => $user->id], ['id' => $element->id])
            ->execute();

    }

    private function _parseTransactions($event, Order $order): void
    {

        $feed = $event->feed;

        $transactions = array();

        foreach ($feed['fieldMapping'] as $fieldHandle => $fieldInfo) {

            if (str_contains($fieldHandle, 'transaction-')) {

                $attribute = str_replace('transaction-', '', $fieldHandle);
                $attributeValue = DataHelper::fetchArrayValue($event->feedData, $fieldInfo);

                $totalLineItems = count($attributeValue);

                for ($i = 0; $i < $totalLineItems; $i++) {
                    $transactions[$i][$attribute] = $attributeValue[$i] ?? null;
                }

                $feed['fieldMapping']['transactions'] = $transactions;

                unset($feed['fieldMapping'][$fieldHandle]);
            }
            
        }

        if (!(new \craft\db\Query())
            ->createCommand()
            ->insert('{{%commerce_transactions}}', [
                'orderId' => $order->id,
                'gatewayId' => $order->gatewayId,
                'userId' => $order->getCustomerId(),
                'hash' => Craft::$app->getSecurity()->generateRandomString(32),
                'type' => TransactionRecord::TYPE_PURCHASE,
                'amount' => $order->getPaymentAmount(),
                'paymentAmount' => $order->getPaymentAmount(),
                'currency' => $order->currency,
                'paymentRate' => '1.0000',
                'status' => TransactionRecord::STATUS_SUCCESS,
                'paymentCurrency' => $order->paymentCurrency,
                'reference' => '',
            ])
            ->execute()) {
            OrderImporter::warn('Failed to insert transaction for order ' . $order->id);
        }

        // $transaction = Commerce::getInstance()->getTransactions()->createTransaction($order, null, TransactionRecord::TYPE_PURCHASE);

        // Commerce::getInstance()->getTransactions()->saveTransaction($transaction);
        // Commerce::getInstance()->getPayments()->captureTransaction($transaction);

        // $transaction->status = TransactionRecord::STATUS_SUCCESS;


        // if (Commerce::getInstance()->getTransactions()->saveTransaction($transaction)) {
        //     $order->updateOrderPaidInformation();
        // }

        // @TODO: Add a transaction for each transaction in the feed

        // foreach ($transactions as $transactionData) {

        //     $transaction = Commerce::getInstance()->getTransactions()->createTransaction($order, null, TransactionRecord::TYPE_PURCHASE);

        //     Commerce::getInstance()->getTransactions()->saveTransaction($transaction);
        //     // Commerce::getInstance()->getPayments()->captureTransaction($transaction);

        //     OrderImporter::log('_parseTransactions: ' . $transaction->id);

        //     $order->updateOrderPaidInformation();

        // }
        
        
    }

    public function getQuery($settings, array $params = []): mixed
    {
        $query = CommerceOrderElement::find()
            ->status(null)
        //   ->typeId($settings['elementGroup'][ProductElement::class])
            ->siteId(Hash::get($settings, 'siteId') ?: Craft::$app->getSites()->getPrimarySite()->id);
        Craft::configure($query, $params);

        return $query;
    }

    public function setModel($settings): ElementInterface
    {
        $this->element = new CommerceOrderElement();

        $siteId = Hash::get($settings, 'siteId');

        if ($siteId) {
            $this->element->siteId = $siteId;
        }

        return $this->element;

    }

    public function getGroups(): array
    {
        return [];
    }

    protected function parseOrderStatusId($feedData, $fieldInfo): int|string|null
    {
        $value = $this->fetchSimpleValue($feedData, $fieldInfo);

        if (array_key_exists($value, $this->_orderStatusCache)) {
            return $this->_orderStatusCache[$value];
        }

        if (is_numeric($value)) {
            $orderStatus = Commerce::getInstance()->getOrderStatuses()->getOrderStatusById($value);
        } else {
            $orderStatus = Commerce::getInstance()->getOrderStatuses()->getOrderStatusByHandle($value);
        }

        $result = $orderStatus?->id;
        $this->_orderStatusCache[$value] = $result;
        return $result;
    }

    /**
     * Parse Customer ID from feed. If the customer doesn't exist,
     * _parseCustomer() will find or create them by email before save.
     */
    protected function parseCustomerId($feedData, $fieldInfo): int|string|null
    {
        return $this->fetchSimpleValue($feedData, $fieldInfo);
    }

    /**
     * @Random Generate UID For Order
     */

    public function UUID()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    protected function parseUid($feedData, $fieldInfo): string
    {
        return $this->UUID();
    }

    protected function parseNumber($feedData, $fieldInfo): string|null
    {
        $value = $this->fetchSimpleValue($feedData, $fieldInfo);
        return md5($value);
    }

    protected function parseReference($feedData, $fieldInfo): string|null
    {
        $value = $this->fetchSimpleValue($feedData, $fieldInfo);
        return substr(md5($value), 0, 7);
    }

    protected function parseDateOrdered($feedData, $fieldInfo): DateTime|string|null
    {
        $value = $this->fetchSimpleValue($feedData, $fieldInfo);

        if ($fieldInfo) {
            $node = $fieldInfo['node'];
            if ($node === 'usedefault') {
                return $value;
            } else {
                $dateValue = DateHelper::parseString($value, self::DATE_FORMAT);
                if ($dateValue instanceof Carbon) {
                    return $dateValue->toDateTime();
                }
            }
        }

        return null;
    }

    protected function parseDateAuthorized($feedData, $fieldInfo): DateTime|string|null
    {
        $value = $this->fetchSimpleValue($feedData, $fieldInfo);

        if ($fieldInfo) {
            $node = $fieldInfo['node'];
            if ($node === 'usedefault') {
                return $value;
            } else {
                $dateValue = DateHelper::parseString($value, self::DATE_FORMAT);
                if ($dateValue instanceof Carbon) {
                    return $dateValue->toDateTime();
                }
            }
        }

        return null;
    }

    protected function parseDatePaid($feedData, $fieldInfo): DateTime|string|null
    {
        $value = $this->fetchSimpleValue($feedData, $fieldInfo);

        if ($fieldInfo) {
            $node = $fieldInfo['node'];
            if ($node === 'usedefault') {
                return $value;
            } else {
                $dateValue = DateHelper::parseString($value, self::DATE_FORMAT);
                if ($dateValue instanceof Carbon) {
                    return $dateValue->toDateTime();
                }
            }
        }

        return null;
    }

    protected function parseGatewayId($feedData, $fieldInfo): int|null
    {
        $value = $this->fetchSimpleValue($feedData, $fieldInfo);

        if (array_key_exists($value, $this->_gatewayCache)) {
            return $this->_gatewayCache[$value];
        }

        $gateway = Commerce::getInstance()->getGateways()->getGatewayByHandle($value);
        $result = $gateway?->id;
        $this->_gatewayCache[$value] = $result;
        return $result;
    }

}
