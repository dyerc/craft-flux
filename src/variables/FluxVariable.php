<?php
/**
 * @copyright Copyright (c) Chris Dyer
 */

namespace dyerc\flux\variables;

use craft\elements\Asset;
use craft\helpers\ImageTransforms;
use dyerc\flux\Flux;

class FluxVariable
{
    public function transform(Asset $asset, mixed $transform): string
    {
        if (is_array($transform)) {
            if (isset($transform['width'])) {
                $transform['width'] = round((float)$transform['width']);
            }
            if (isset($transform['height'])) {
                $transform['height'] = round((float)$transform['height']);
            }
        }

        $transform = ImageTransforms::normalizeTransform($transform);

        return Flux::getInstance()->transformer->getUrl($asset, $transform);
    }
}