<?php
/**
 * @copyright Copyright (c) Chris Dyer
 */

namespace dyerc\flux\assetbundles;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;
use craft\web\assets\vue\VueAsset;

class FluxAssets extends AssetBundle
{
    public function init()
    {
        $this->sourcePath = "@dyerc/flux/web/assets/dist/assets";

        $this->depends = [
            CpAsset::class,
            VueAsset::class,
        ];

        $this->js = [
            'flux.js'
        ];

        $this->css = [
            'flux.css'
        ];

        parent::init();
    }
}