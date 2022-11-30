<?php
/**
 * @copyright Copyright (c) Chris Dyer
 */

namespace dyerc\flux\jobs;

use Craft;
use craft\elements\Asset;
use craft\queue\BaseJob;
use dyerc\flux\Flux;

class PurgeAssetJob extends BaseJob
{
    public int|null $assetId;

    protected function defaultDescription(): ?string
    {
        return "Purging asset from AWS";
    }

    public function execute($queue): void
    {
        if ($this->assetId) {
            $asset = Asset::find()->id($this->assetId)->one();

            if ($asset) {
                Flux::getInstance()->cloudfront->purgeAsset($asset);
            }
        }
    }
}