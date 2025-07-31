<?php

namespace bymayo\craftorderimporter;

use bymayo\craftorderimporter\integrations\CommerceOrder;

use Craft;
use craft\base\Plugin as BasePlugin;
use craft\helpers\FileHelper;

use craft\feedme\events\RegisterFeedMeElementsEvent;
use craft\feedme\services\Elements;

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

        $file = Craft::getAlias('@storage/logs/order-importer.log');
        $log = date('Y-m-d H:i:s'). ' ' . $message . "\n";
        FileHelper::writeToFile($file, $log, ['append' => true]);

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

        Craft::$app->onInit(function() {
            $this->attachEventHandlers();
        });
    }

    private function attachEventHandlers(): void
    {

        Event::on(
            Elements::class, 
            Elements::EVENT_REGISTER_FEED_ME_ELEMENTS, 
            function(RegisterFeedMeElementsEvent $e) {

                $this->log('Registering CommerceOrder element');

                $e->elements[] = CommerceOrder::class;
            }
        );

    }
}
