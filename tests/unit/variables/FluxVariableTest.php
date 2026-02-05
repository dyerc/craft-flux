<?php

/**
 * @copyright Copyright (c) Chris Dyer
 */

namespace dyerc\fluxtests\unit\variables;

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
        Flux::$plugin->settings->cloudFrontDomain = 'cloudfront';
        Flux::$plugin->settings->verifySecret = 'secret';

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

        $this->flux = new FluxVariable;
    }

    protected function _removeCacheKeys($str): string
    {
        $str = preg_replace("/&c=\w+/", '', $str);
        $str = preg_replace("/&amp;c=\w+/", '', $str);

        return $str;
    }

    public function testGeneratesUrlEvenWhenFluxDisabled(): void
    {
        Flux::$plugin->settings->enabled = false;
        Flux::$plugin->settings->verifyQuery = false;

        $this->assertSame(
            'https://cloudfront/volume/foo.jpg?mode=fit&pos=center-center&w=1920&h=1080',
            $this->_removeCacheKeys($this->flux->transform($this->asset, [
                'mode' => 'fit',
                'width' => 1920,
                'height' => 1080,
            ]))
        );
    }

    public function testGeneratesUrlWithBlurFilter(): void
    {
        Flux::$plugin->settings->verifyQuery = false;

        $this->assertSame(
            'https://cloudfront/volume/foo.jpg?mode=fit&pos=center-center&w=1920&h=1080&blur=true',
            $this->_removeCacheKeys($this->flux->transform($this->asset, [
                'mode' => 'fit',
                'width' => 1920,
                'height' => 1080,
            ], [
                'blur' => true,
            ]))
        );
    }

    public function testGeneratesUrlWithGaussianBlurFilter(): void
    {
        Flux::$plugin->settings->verifyQuery = false;

        $this->assertSame(
            'https://cloudfront/volume/foo.jpg?mode=fit&pos=center-center&w=1920&h=1080&blur=0.3',
            $this->_removeCacheKeys($this->flux->transform($this->asset, [
                'mode' => 'fit',
                'width' => 1920,
                'height' => 1080,
            ], [
                'blur' => 0.3,
            ]))
        );
    }

    public function testIgnoresOutOfBoundsBlurFactor(): void
    {
        Flux::$plugin->settings->verifyQuery = false;

        $this->assertSame(
            'https://cloudfront/volume/foo.jpg?mode=fit&pos=center-center&w=1920&h=1080',
            $this->_removeCacheKeys($this->flux->transform($this->asset, [
                'mode' => 'fit',
                'width' => 1920,
                'height' => 1080,
            ], [
                'blur' => 0.1,
            ]))
        );

        $this->assertSame(
            'https://cloudfront/volume/foo.jpg?mode=fit&pos=center-center&w=1920&h=1080',
            $this->_removeCacheKeys($this->flux->transform($this->asset, [
                'mode' => 'fit',
                'width' => 1920,
                'height' => 1080,
            ], [
                'blur' => -10.1,
            ]))
        );

        $this->assertSame(
            'https://cloudfront/volume/foo.jpg?mode=fit&pos=center-center&w=1920&h=1080',
            $this->_removeCacheKeys($this->flux->transform($this->asset, [
                'mode' => 'fit',
                'width' => 1920,
                'height' => 1080,
            ], [
                'blur' => 2000,
            ]))
        );
    }

    public function testGeneratesUrlWithGreyscaleFilter(): void
    {
        Flux::$plugin->settings->verifyQuery = false;

        $this->assertSame(
            'https://cloudfront/volume/foo.jpg?mode=fit&pos=center-center&w=1920&h=1080&greyscale=1',
            $this->_removeCacheKeys($this->flux->transform($this->asset, [
                'mode' => 'fit',
                'width' => 1920,
                'height' => 1080,
            ], [
                'greyscale' => true,
            ]))
        );
    }

    public function testGeneratesUrlWithTintFilter(): void
    {
        Flux::$plugin->settings->verifyQuery = false;

        $this->assertSame(
            'https://cloudfront/volume/foo.jpg?mode=fit&pos=center-center&w=1920&h=1080&tint=255%2C255%2C255',
            $this->_removeCacheKeys($this->flux->transform($this->asset, [
                'mode' => 'fit',
                'width' => 1920,
                'height' => 1080,
            ], [
                'tint' => ['r' => 255, 'g' => 255, 'b' => 255],
            ]))
        );
    }

    public function testGeneratesUrlWithTintFilterAsHex(): void
    {
        Flux::$plugin->settings->verifyQuery = false;

        $this->assertSame(
            'https://cloudfront/volume/foo.jpg?mode=fit&pos=center-center&w=1920&h=1080&tint=255%2C102%2C0',
            $this->_removeCacheKeys($this->flux->transform($this->asset, [
                'mode' => 'fit',
                'width' => 1920,
                'height' => 1080,
            ], [
                'tint' => '#FF6600',
            ]))
        );
    }

    public function testGeneratesLqipUrl(): void
    {
        Flux::$plugin->settings->verifyQuery = false;

        $this->assertSame(
            'https://cloudfront/volume/foo.jpg?mode=crop&pos=center-center&w=200&h=150&blur=25',
            $this->_removeCacheKeys($this->flux->lqip($this->asset))
        );
    }
}
