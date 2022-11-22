<?php
/**
 * @copyright Copyright (c) Chris Dyer
 */

namespace dyerc\flux\controllers;

use Craft;
use craft\helpers\App;
use craft\helpers\ArrayHelper;
use craft\web\Controller;
use yii\web\Response;
use dyerc\flux\Flux;

class SettingsController extends Controller {
    public function actionEdit(): ?Response
    {
        $settings = Flux::$plugin->settings;

        if (Craft::$app->request->getParam('wizard')) {
            return $this->renderTemplate('flux/_wizard', [
                'settings' => $settings,
                'config' => Craft::$app->getConfig()->getConfigFromFile('flux')
            ]);
        } else {
            return $this->renderTemplate('flux/_settings', [
                'settings' => $settings,
                'config' => Craft::$app->getConfig()->getConfigFromFile('flux')
            ]);
        }
    }

    public function actionSave(): ?Response
    {
        $this->requirePostRequest();
        $request = Craft::$app->getRequest();

        $postedSettings = $request->getBodyParam('settings', []);

        if ($postedSettings['bucketSelectionMode'] === "manual") {
            if (isset($postedSettings['manualBucket'])) {
                $postedSettings['awsBucket'] = ArrayHelper::remove($postedSettings, 'manualBucket');
            }

            if (isset($postedSettings['manualRegion'])) {
                $postedSettings['awsRegion'] = ArrayHelper::remove($postedSettings, 'manualRegion');
            }
        } else {
            unset($postedSettings['manualBucket'], $postedSettings['manualRegion']);
        }

        if ($postedSettings['distributionSelectionMode'] === "manual") {
            if (isset($postedSettings['manualCloudFrontDistributionId'])) {
                $postedSettings['cloudFrontDistributionId'] = ArrayHelper::remove($postedSettings, 'manualCloudFrontDistributionId');
            }

            if (isset($postedSettings['manualCloudfrontDomain'])) {
                $postedSettings['cloudFrontDomain'] = ArrayHelper::remove($postedSettings, 'manualCloudfrontDomain');
            }
        } else {
            unset($postedSettings['manualCloudFrontDistributionId'], $postedSettings['manualCloudfrontDomain']);
        }

        $settings = Flux::$plugin->settings;
        $settings->setAttributes($postedSettings, false);

        $settings->validate();

        if ($settings->hasErrors()) {
            Craft::$app->getSession()->setError(Craft::t('flux', 'Couldn’t save plugin settings.'));

            return null;
        }

        Craft::$app->getPlugins()->savePluginSettings(Flux::$plugin, $settings->getAttributes());
        Craft::$app->getSession()->setNotice(Craft::t('flux', 'Plugin settings saved.'));

        return $this->redirectToPostedUrl();
    }

    public function actionWizardTest(): ?Response
    {
        $this->requirePostRequest();
        $request = Craft::$app->getRequest();

        $postedSettings = $request->getBodyParam('settings', []);
        $postedSettings['awsRegion'] = "us-east-1";

        $settings = Flux::$plugin->settings;
        $settings->setAttributes($postedSettings, false);
        Craft::$app->getPlugins()->savePluginSettings(Flux::$plugin, $settings->getAttributes());

        $bucketReady = false;
        $distributionReady = false;

        // Test provided settings by querying for S3 region and CloudFront domain
        try {
            $buckets = Flux::getInstance()->s3->getBuckets();

            foreach ($buckets as $bucket) {
                if ($bucket['bucket'] == App::parseEnv($postedSettings['manualBucket'])) {
                    $postedSettings['awsBucket'] = ArrayHelper::remove($postedSettings, 'manualBucket');
                    $postedSettings['awsRegion'] = $bucket['region'];
                    $bucketReady = true;
                    break;
                }
            }

            $distributions = Flux::getInstance()->cloudfront->getDistributions();

            foreach ($distributions as $distro) {
                if ($distro['id'] == App::parseEnv($postedSettings['manualCloudFrontDistributionId'])) {
                    $postedSettings['cloudFrontDistributionId'] = ArrayHelper::remove($postedSettings, 'manualCloudFrontDistributionId');
                    $postedSettings['cloudFrontDomain'] = $distro['domain'];
                    $distributionReady = true;
                    break;
                }
            }
        } catch (\Exception $e) {
            Craft::error($e->getTraceAsString());
        }

        if (!$bucketReady) {
            Craft::$app->getSession()->setError(Craft::t('flux', 'Unable to access S3 bucket'));
        } else if (!$distributionReady) {
            Craft::$app->getSession()->setError(Craft::t('flux', 'Unable to access CloudFront distribution'));
        }

        if ($bucketReady) {
            $settings->setAttributes($postedSettings, false);
            $settings->validate();

            if ($settings->hasErrors()) {
                Craft::$app->getSession()->setError(Craft::t('flux', 'Couldn’t save plugin settings.'));

                return null;
            }

            Craft::$app->getPlugins()->savePluginSettings(Flux::$plugin, $settings->getAttributes());
            return $this->redirect('settings/plugins/flux?wizard=ready');
        } else {
            return $this->redirect('settings/plugins/flux?wizard=1');
        }
    }
}