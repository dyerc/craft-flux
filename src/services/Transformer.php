<?php
/**
 * @copyright Copyright (c) Chris Dyer
 */

namespace dyerc\flux\services;

use craft\elements\Asset;
use craft\models\ImageTransform;
use dyerc\flux\Flux;
use dyerc\flux\models\SettingsModel;
use yii\base\Component;

class Transformer extends Component
{
    public function getPath(Asset $asset): string
    {
        $pathComponents = [
            $asset->volume->handle,
            $asset->path
        ];

        return join("/", array_filter($pathComponents, function ($p) {
            return $p && strlen($p) > 0;
        }));
    }

    public function getUrl(Asset $asset, ImageTransform $transform): string
    {
        /* @var SettingsModel */
        $settings = Flux::getInstance()->getSettings();

        $root = $settings->getCloudfrontEndpoint();
        $path = $this->getPath($asset);

        $transformKeys = [
            'mode' => $transform->mode,
            'pos' => $transform->position
        ];

        if ($asset->getHasFocalPoint()) {
            $focal = $asset->getFocalPoint();
            $transformKeys['pos'] = $focal['x'] . '-' . $focal['y'];
        } elseif (!preg_match('/(top|center|bottom)-(left|center|right)/', $transform->position)) {
            $transformKeys['pos'] = 'center-center';
        } else {
            $transformKeys['pos'] = $transform->position;
        }

        if ($transform->width) {
            $transformKeys['w'] = $transform->width;
        }

        if ($transform->height) {
            $transformKeys['h'] = $transform->height;
        }

        if ($transform->quality) {
            $transformKeys['q'] = $transform->quality;
        }

        if ($transform->format) {
            $transformKeys['f'] = $transform->format;
        }

        $path .= '?' . http_build_query($transformKeys);

        // Must be handled last so that only the hmac is appended after hashing
        if ($settings->verifyQuery) {
            $verify = hash_hmac('sha256', $path, $settings->verifySecret);
            $path .= "&v=" . $verify;
        }

        return $root . $path;
    }
}