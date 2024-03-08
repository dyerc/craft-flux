<?php
/**
 * Flux plugin for Craft CMS 4.x
 *
 * Transform and cache your images through AWS Lambda & CloudFront
 *
 * @link      https://cdyer.co.uk
 * @copyright Copyright (c) 2022 Chris Dyer
 */

namespace dyerc\flux;

use Craft;
use craft\base\Element;
use craft\base\Model;
use craft\base\Plugin;
use craft\elements\Asset;
use craft\events\GenerateTransformEvent;
use craft\events\ModelEvent;
use craft\events\PluginEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\Queue;
use craft\helpers\UrlHelper;
use craft\services\Plugins;
use craft\services\Utilities;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use dyerc\flux\jobs\PurgeAssetJob;
use dyerc\flux\models\SettingsModel;
use dyerc\flux\services\Aws;
use dyerc\flux\services\Cloudfront;
use dyerc\flux\services\Lambda;
use dyerc\flux\services\S3;
use dyerc\flux\services\Transformer;
use dyerc\flux\utilities\FluxUtility;
use dyerc\flux\variables\FluxVariable;
use yii\base\Event;

/**
 * @property-read Aws $aws
 * @property-read Cloudfront $cloudfront
 * @property-read Lambda $lambda
 * @property-read S3 $s3
 * @property-read Transformer $transformer
 *
 * @property-read SettingsModel $settings
 */
class Flux extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * @var null|Flux
     */
    public static ?Flux $plugin;

    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public string $schemaVersion = '1.0.0';

    /**
     * @var bool
     */
    public bool $hasCpSettings = true;

    /**
     * @var bool
     */
    public bool $hasCpSection = false;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        self::$plugin = $this;

        /* @var SettingsModel */
        $settings = $this->getSettings();

        $this->_registerServices();
        $this->_registerVariables();
        $this->_registerDefaults();
        $this->_registerEvents();

        // Register control panel events
        if (Craft::$app->getRequest()->getIsCpRequest()) {
            $this->_registerCpUrlRules();
            $this->_registerUtilities();
            $this->_registerRedirectAfterInstall();
        }

        Craft::info(
            Craft::t(
                'flux',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
    }

    protected function createSettingsModel(): ?Model
    {
        return new SettingsModel();
    }

    private function _registerEvents(): void
    {
        /* @var SettingsModel */
        $settings = $this->getSettings();

        Event::on(Asset::class, Asset::EVENT_BEFORE_GENERATE_TRANSFORM,
            function (GenerateTransformEvent $event) use ($settings) {
                if ($settings->enabled) {
                    $event->url = $this->transformer->getUrl($event->asset, $event->transform);
                }
            }
        );

        /*
         *  Purge all transformed versions when the asset is changed. Image manipulation through
         *  the admin area such as cropping seems to show up as a change without any dirty
         *  attributes/fields, so purge everything, no matter how minor the change.
         */
        Event::on(Asset::class, Element::EVENT_BEFORE_SAVE,
            function (ModelEvent $event) use ($settings) {
                if ($event->isNew) {
                    return;
                }

                if ($settings->enabled && $settings->autoPurgeAssets) {
                    /* @var Asset $asset */
                    $asset = $event->sender;

                    Queue::push(new PurgeAssetJob([
                        'assetId' => $asset->id
                    ]));
                }
            }
        );

        /*
         *  Purge transformed versions before an asset is deleted
         */
        Event::on(Asset::class, Element::EVENT_BEFORE_DELETE,
            function (ModelEvent $event) use ($settings) {
                /* @var SettingsModel */
                $settings = $this->getSettings();

                if ($settings->enabled && $settings->autoPurgeAssets) {
                    /* @var Asset $asset */
                    $asset = $event->sender;
                    Queue::push(new PurgeAssetJob([
                        'assetId' => $asset->id
                    ]));
                }
            }
        );
    }

    private function _registerDefaults(): void
    {
        /* @var SettingsModel */
        $settings = $this->getSettings();

        if (empty($settings->verifySecret)) {
            try {
                Craft::$app->plugins->savePluginSettings(self::$plugin, [
                    'verifySecret' => Craft::$app->security->generateRandomString(12)
                ]);
            } catch (\Exception $e) {
                Craft::error("Unable to generate verification secret", __METHOD__);
            }
        }
    }

    private function _registerServices(): void
    {
        $this->setComponents([
            'aws' => Aws::class,
            'cloudfront' => Cloudfront::class,
            'lambda' => Lambda::class,
            's3' => S3::class,
            'transformer' => Transformer::class
        ]);
    }

    private function _registerVariables(): void
    {
        Event::on(CraftVariable::class, CraftVariable::EVENT_INIT,
            function(Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('flux', FluxVariable::class);
            }
        );
    }

    /**
     * Registers CP URL rules event
     */
    private function _registerCpUrlRules(): void
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                // Merge so that settings controller action comes first (important!)
                $event->rules = array_merge([
                    'settings/plugins/flux' => 'flux/settings/edit',
                ],
                    $event->rules
                );
            }
        );
    }

    /**
     * Registers utilities
     */
    private function _registerUtilities(): void
    {
        Event::on(Utilities::class, Utilities::EVENT_REGISTER_UTILITIES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = FluxUtility::class;
            }
        );
    }

    /**
     * Registers redirect after install
     */
    private function _registerRedirectAfterInstall(): void
    {
        Event::on(Plugins::class, Plugins::EVENT_AFTER_INSTALL_PLUGIN,
            function(PluginEvent $event) {
                if ($event->plugin === $this) {
                    // Redirect to settings page with welcome
                    Craft::$app->getResponse()->redirect(
                        UrlHelper::cpUrl('settings/plugins/flux', [
                            'wizard' => 1,
                        ])
                    )->send();
                }
            }
        );
    }
}
