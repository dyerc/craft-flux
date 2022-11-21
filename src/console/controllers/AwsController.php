<?php
/**
 * @copyright Copyright (c) Chris Dyer
 */

namespace dyerc\flux\console\controllers;

use Craft;
use dyerc\flux\Flux;
use craft\console\Controller;
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
        $lambda = Flux::getInstance()->lambda;

        if (Flux::getInstance()->version != $lambda->getInstalledVersion()) {
            return $this->actionInstall();
        } else {
            $this->stdout(Craft::t('flux', 'Flux version ' . Flux::getInstance()->version . ' is installed and up-to-date'). PHP_EOL);
            return ExitCode::OK;
        }
    }
}