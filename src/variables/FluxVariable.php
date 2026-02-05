<?php
/**
 * @copyright Copyright (c) Chris Dyer
 */

namespace dyerc\flux\variables;

use craft\elements\Asset;
use craft\helpers\ImageTransforms;
use dyerc\flux\Flux;
use dyerc\flux\models\ImageFilters;

class FluxVariable
{
    public const MIN_LQIP_WIDTH = 20;
    public const MIN_LQIP_HEIGHT = 20;

    public function transform(Asset $asset, mixed $transform, mixed $filters = null): string
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
        $filters = ImageFilters::normalize($filters);

        return Flux::getInstance()->transformer->getUrl($asset, $transform, $filters);
    }

    public function lqip(Asset $asset): string
    {
        return $this->transform($asset, [
            'width' => max($asset->width / 4, self::MIN_LQIP_WIDTH),
            'height' => max($asset->height / 4, self::MIN_LQIP_HEIGHT),
        ], [
            'blur' => 25
        ]);
    }
}