<?php
/**
 * @copyright Copyright (c) Chris Dyer
 */

namespace dyerc\flux\jobs;

use Craft;
use craft\queue\BaseJob;
use dyerc\flux\Flux;

class PurgeCloudFrontJob extends BaseJob
{
    protected function defaultDescription(): ?string
    {
        return "Purging CloudFront";
    }

    public function execute($queue): void
    {
        $volumes = Craft::$app->getVolumes()->getAllVolumes();

        foreach ($volumes as $volume) {
            Flux::getInstance()->s3->purgeAllTransformedVersions($volume);
        }

        Flux::getInstance()->cloudfront->invalidateCache();
    }
}