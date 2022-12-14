<?php
/**
 * @copyright Copyright (c) Chris Dyer
 */

namespace dyerc\flux\jobs;

use Craft;
use craft\queue\BaseJob;
use dyerc\flux\Flux;

class LambdaDeployJob extends BaseJob
{
    protected function defaultDescription(): ?string
    {
        return "Updating AWS Flux Installation";
    }

    public function execute($queue): void
    {
        $lambda = Flux::getInstance()->lambda;

        $viewerRequestArn = $lambda->deployViewerRequestFunction();
        $this->setProgress($queue, 0.4, Craft::t('app', 'Deployed viewer request Lambda function'));

        $originResponseArn = $lambda->deployOriginResponseFunction();
        $this->setProgress($queue, 0.8, Craft::t('app', 'Deployed origin response Lambda function'));

        $lambda->publishNewVersion($viewerRequestArn, $originResponseArn);
        $this->setProgress($queue, 1, Craft::t('app', 'Published new AWS version'));
    }
}