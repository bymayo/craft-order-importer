<?php

namespace bymayo\craftorderimporter;

use bymayo\craftorderimporter\integrations\CommerceOrder;

use Craft;
use craft\base\Plugin as BasePlugin;
use craft\log\MonologTarget;

use craft\feedme\events\RegisterFeedMeElementsEvent;
use craft\feedme\services\Elements;

use Psr\Log\LogLevel;
use yii\base\Event;

/**
 * Order Importer plugin
 *
 * @method static Plugin getInstance()
 * @author Jason Mayo <jason@bymayo.co.uk>
 * @copyright Jason Mayo
 * @license https://craftcms.github.io/license/ Craft License
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '1.0.0';

    public static $plugin;

    public static function log($message)
    {
        Craft::info($message, 'order-importer');
    }

    public static function warn($message)
    {
        Craft::warning($message, 'order-importer');
    }

    public static function config(): array
    {
        return [
            'components' => [],
        ];
    }

    public function init(): void
    {
        parent::init();

        $this->_registerLogTarget();

        Craft::$app->onInit(function() {
            $this->attachEventHandlers();
        });
    }

    private function _registerLogTarget(): void
    {
        Craft::getLogger()->dispatcher->targets[] = new MonologTarget([
            'name' => 'order-importer',
            'categories' => ['order-importer'],
            'level' => LogLevel::INFO,
            'logContext' => false,
            'allowLineBreaks' => false,
        ]);
    }

    private function attachEventHandlers(): void
    {

        Event::on(
            Elements::class, 
            Elements::EVENT_REGISTER_FEED_ME_ELEMENTS, 
            function(RegisterFeedMeElementsEvent $e) {

                $e->elements[] = CommerceOrder::class;
            }
        );

    }
}
