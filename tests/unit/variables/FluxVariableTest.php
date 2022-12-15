<?php
/**
 * @copyright Copyright (c) Chris Dyer
 */

namespace dyerc\fluxtests\unit\variables;

use Craft;
use craft\base\Fs;
use craft\elements\Asset;
use craft\models\Volume;
use craft\test\TestCase;
use dyerc\flux\Flux;
use dyerc\flux\variables\FluxVariable;

class FluxVariableTest extends TestCase
{
    /**
     * @var UnitTester
     */
    protected $tester;

    private Asset|\PHPUnit\Framework\MockObject\MockObject $asset;
    private FluxVariable $flux;

    protected function _before()
    {
        parent::_before();

        Flux::$plugin->settings->enabled = true;
        Flux::$plugin->settings->cloudFrontDomain = "cloudfront";
        Flux::$plugin->settings->verifySecret = "secret";

        $this->asset = $this->make(Asset::class, [
            'getVolume' => $this->make(Volume::class, [
                'getFs' => $this->make(Fs::class, [
                    'hasUrls' => true,
                ]),
                'getTransformFs' => $this->make(Fs::class, [
                    'hasUrls' => true,
                ]),
                'handle' => 'volume',
            ]),
            'folderId' => 2,
            'kind' => 'image',
            '_width' => 800,
            '_height' => 600,
            'filename' => 'foo.jpg',
        ]);

        $this->flux = new FluxVariable();
    }

    protected function _removeCacheKeys($str): string
    {
        $str = preg_replace("/&c=\w+/", "", $str);
        $str = preg_replace("/&amp;c=\w+/", "", $str);
        return $str;
    }

    public function testGeneratesUrlEvenWhenFluxDisabled(): void
    {
        Flux::$plugin->settings->enabled = false;
        Flux::$plugin->settings->verifyQuery = false;

        $this->assertSame(
            "https://cloudfront/volume/foo.jpg?mode=fit&pos=center-center&w=1920&h=1080",
            $this->_removeCacheKeys($this->flux->transform($this->asset, [
                'mode' => 'fit',
                'width' => 1920,
                'height' => 1080
            ]))
        );
    }
}