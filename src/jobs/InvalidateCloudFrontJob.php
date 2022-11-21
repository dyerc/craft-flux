<?php
/**
 * @copyright Copyright (c) Chris Dyer
 */

namespace dyerc\flux\jobs;

use craft\queue\BaseJob;
use dyerc\flux\Flux;

class InvalidateCloudFrontJob extends BaseJob
{
    protected function defaultDescription(): ?string
    {
        return "Invalidating CloudFront";
    }

    public function execute($queue): void
    {
        Flux::getInstance()->cloudfront->invalidateCache();
    }
}