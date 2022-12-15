<?php
/**
 * @copyright Copyright (c) Chris Dyer
 */

namespace dyerc\flux\console\controllers;

use Craft;
use dyerc\flux\Flux;
use craft\console\Controller;
use dyerc\flux\models\SettingsModel;
use yii\console\ExitCode;

class AwsController extends Controller
{
    /**
     * @inheritdoc
     */
    public $defaultAction = 'install';

    /**
     * Installs Flux to AWS
     *
     * @return int
     */
    public function actionInstall(): int
    {
        $lambda = Flux::getInstance()->lambda;

        $this->stdout(Craft::t('flux', 'Deploying viewer request Lambda function'). PHP_EOL);
        $viewerRequestArn = $lambda->deployViewerRequestFunction();

        $this->stdout(Craft::t('flux', 'Deploying origin response Lambda function'). PHP_EOL);
        $originResponseArn = $lambda->deployOriginResponseFunction();

        $this->stdout(Craft::t('flux', 'Publishing new version to AWS'). PHP_EOL);
        $lambda->publishNewVersion($viewerRequestArn, $originResponseArn);

        return ExitCode::OK;
    }

    /**
     * Checks and installs Flux if it is outdated or not currently installed
     *
     * @return int
     */
    public function actionUpdate(): int
    {
        /* @var SettingsModel */
        $settings = Flux::getInstance()->getSettings();

        $lambda = Flux::getInstance()->lambda;
        $status = $lambda->getFunctionStatuses();
        $configHash = $settings->lambdaConfigHash();

        if (Flux::getInstance()->version != $lambda->getInstalledVersion($status)) {
            $this->stdout(Craft::t('flux', 'Updating Flux installation to version ' . Flux::getInstance()->version) . PHP_EOL);
            return $this->actionInstall();
        } else if ($lambda->getConfigVersion($status) != $configHash) {
            $this->stdout(Craft::t('flux', 'Flux installed configuration is outdated, reinstalling version ' . Flux::getInstance()->version) . PHP_EOL);
            return $this->actionInstall();
        } else {
            $this->stdout(Craft::t('flux', 'Flux version ' . Flux::getInstance()->version . ' is installed and up-to-date'). PHP_EOL);
            return ExitCode::OK;
        }
    }

    /**
     * Purges all transformed assets and invalidates CloudFront
     *
     * @return int
     */
    public function actionPurge(): int
    {
        $volumes = Craft::$app->getVolumes()->getAllVolumes();

        foreach ($volumes as $volume) {
            Flux::getInstance()->s3->purgeAllTransformedVersions($volume);
        }

        Flux::getInstance()->cloudfront->invalidateCache();

        return ExitCode::OK;
    }
}