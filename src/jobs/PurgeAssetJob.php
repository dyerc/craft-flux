<?php
/**
 * @copyright Copyright (c) Chris Dyer
 */

namespace dyerc\flux\jobs;

use craft\elements\Asset;
use craft\queue\BaseJob;
use dyerc\flux\Flux;

class PurgeAssetJob extends BaseJob
{
    public Asset $asset;

    protected function defaultDescription(): ?string
    {
        return "Purging asset from AWS";
    }

    public function execute($queue): void
    {
        Flux::getInstance()->cloudfront->purgeAsset($this->asset);
    }
}