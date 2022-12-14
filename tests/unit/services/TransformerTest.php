<?php
/**
 * @copyright Copyright (c) Chris Dyer
 */

namespace dyerc\fluxtests\unit\services;

use Craft;
use craft\base\Fs;
use craft\elements\Asset;
use craft\imagetransforms\ImageTransformer;
use craft\models\ImageTransform;
use craft\models\Volume;
use craft\services\ImageTransforms;
use craft\test\TestCase;
use dyerc\flux\Flux;
use UnitTester;

class TransformerTest extends TestCase
{
    /**
     * @var UnitTester
     */
    protected $tester;

    private Asset|\PHPUnit\Framework\MockObject\MockObject $asset;

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
    }

    protected function _removeCacheKeys($str): string
    {
        $str = preg_replace("/&c=\w+/", "", $str);
        $str = preg_replace("/&amp;c=\w+/", "", $str);
        return $str;
    }

    public function testGeneratesUrlForAssetWithVerification(): void
    {
        $cacheKey = Flux::getInstance()->transformer->getCacheKey($this->asset);
        $expected = "volume/foo.jpg?mode=fit&pos=center-center&w=1920&h=1080&c=" . $cacheKey;
        $v = hash_hmac('sha256', $expected, Flux::$plugin->settings->verifySecret);

        $this->assertSame(
            "https://cloudfront/$expected&v=$v",
            $this->asset->getUrl([
                'mode' => 'fit',
                'width' => 1920,
                'height' => 1080
            ])
        );
    }

    public function testGeneratesUrlForAssetWithoutVerification(): void
    {
        Flux::$plugin->settings->verifyQuery = false;

        $this->assertSame(
            "https://cloudfront/volume/foo.jpg?mode=fit&pos=center-center&w=1920&h=1080",
            $this->_removeCacheKeys($this->asset->getUrl([
                'mode' => 'fit',
                'width' => 1920,
                'height' => 1080
            ]))
        );
    }

    public function testDoesntGenerateUrlWhenDisabled(): void
    {
        Flux::$plugin->settings->enabled = false;

        // Stub the builtin transform process
        Craft::$app->set('imageTransforms', $this->make(ImageTransforms::class, [
            'getTransformByHandle' => $this->make(ImageTransform::class, [
                'width' => 400,
                'height' => 200,
                'getImageTransformer' => $this->make(ImageTransformer::class, [
                    'getTransformUrl' => fn(Asset $asset, ImageTransform $transform) => 'w=' . $transform->width . '&h=' . $transform->height,
                ]),
            ])
        ]));

        $this->assertSame(
            "w=400&h=200",
            $this->_removeCacheKeys(
                $this->asset->getUrl([ 'transform' => 'mockedTransform' ])
            )
        );
    }

    public function testGeneratesUrlForAssetWithNamedTransform(): void
    {
        Flux::$plugin->settings->verifyQuery = false;

        Craft::$app->set('imageTransforms', $this->make(ImageTransforms::class, [
            'getTransformByHandle' => $this->make(ImageTransform::class, [
                'width' => 400,
                'height' => 200,
                'getImageTransformer' => $this->make(ImageTransformer::class, [
                    'getTransformUrl' => fn(Asset $asset, ImageTransform $transform) => 'w=' . $transform->width . '&h=' . $transform->height,
                ]),
            ])
        ]));

        $this->assertSame(
            "https://cloudfront/volume/foo.jpg?mode=crop&pos=center-center&w=400&h=200",
            $this->_removeCacheKeys(
                $this->asset->getUrl([
                    'transform' => 'mockedTransform'
                ])
            )
        );
    }

    public function testGeneratesImgMarkup(): void
    {
        Flux::$plugin->settings->verifyQuery = false;

        $this->assertSame(
            "<img src=\"https://cloudfront/volume/foo.jpg?mode=crop&amp;pos=center-center&amp;w=800\" width=\"800\" height=\"600\">",
            $this->_removeCacheKeys(
                (string)$this->asset->getImg([ 'width' => 800 ])
            )
        );
    }

    public function testGeneratesSrcset(): void
    {
        Flux::$plugin->settings->verifyQuery = false;

        $this->assertSame(
            "https://cloudfront/volume/foo.jpg?mode=crop&pos=center-center&w=800, https://cloudfront/volume/foo.jpg?mode=crop&pos=center-center&w=1600 2x",
            $this->_removeCacheKeys(
                $this->asset->getSrcset(['1x', '2x'], ['width' => 800])
            )
        );
    }
}