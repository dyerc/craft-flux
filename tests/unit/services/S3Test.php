<?php
/**
 * @copyright Copyright (c) Chris Dyer
 */

namespace dyerc\fluxtests\unit\services;

use Codeception\Stub\Expected;

use craft\awss3\Fs as AwsFs;
use craft\base\Fs;
use craft\elements\Asset;
use craft\models\Volume;
use craft\test\TestCase;
use dyerc\flux\Flux;
use dyerc\flux\services\S3;
use UnitTester;

class S3Test extends TestCase
{
    /**
     * @var UnitTester
     */
    protected $tester;

    public function testPurgesAsset()
    {
        $asset = $this->make(Asset::class, [
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
            'filename' => 'foo.jpg',
        ]);

        Flux::getInstance()->set('s3', $this->make(S3::class, [
            'listObjects' => Expected::once(function () {
                return [
                    'Flux/volume/foo.webp',
                    'Flux/volume/foo.jpg',
                    'Flux/volume/bar.jpg',
                    'Flux/volume/_159x240_crop_center-center_80/foo.jpg',
                    'Flux/volume/_159x240_crop_center-center_80/foo.webp',
                    'Flux/volume/_159x240_crop_center-center_80/bar.jpg',
                    'Flux/volume/another/_159x240_crop_center-center_80/foo.webp',
                    'Flux/volume/_159x240_crop_center-center_80/another/foo.webp',
                ];
            }),
            'deleteObjects' => Expected::once(function ($objects) {
                $this->assertSame([
                    'Flux/volume/foo.jpg',
                    'Flux/volume/_159x240_crop_center-center_80/foo.jpg',
                    'Flux/volume/_159x240_crop_center-center_80/foo.webp',
                    'Flux/volume/another/_159x240_crop_center-center_80/foo.webp'
                ], $objects);
            })
        ]));

        Flux::getInstance()->s3->purgeTransformedVersions($asset);
    }

    public function testPurgesAssetFromS3()
    {
        Flux::$plugin->settings->rootPrefix = "";

        $asset = $this->make(Asset::class, [
            'getVolume' => $this->make(Volume::class, [
                'getFs' => $this->make(AwsFs::class, [
                    'hasUrls' => true,
                    'subfolder' => 'volume'
                ]),
                'getTransformFs' => $this->make(Fs::class, [
                    'hasUrls' => true,
                ]),
                'handle' => 'volume',
            ]),
            'folderId' => 2,
            'filename' => 'foo.jpg',
        ]);

        Flux::getInstance()->set('s3', $this->make(S3::class, [
            'listObjects' => Expected::once(function () {
                return [
                    'volume/foo.jpg',
                    'volume/bar.jpg',
                    'volume/_159x240_crop_center-center_80/foo.jpg',
                    'volume/_159x240_crop_center-center_80/bar.jpg',
                ];
            }),
            'deleteObjects' => Expected::once(function ($objects) {
                $this->assertSame([
                    // Must not contain "Flux/volume/foo.jpg",
                    "volume/_159x240_crop_center-center_80/foo.jpg"
                ], $objects);
            })
        ]));

        Flux::getInstance()->s3->purgeTransformedVersions($asset);
    }

    public function testPurgesAllAssets()
    {
        Flux::$plugin->settings->rootPrefix = "Flux";

        $volume = $this->make(Volume::class, [
            'getFs' => $this->make(Fs::class, [
                'hasUrls' => true,
            ]),
            'getTransformFs' => $this->make(Fs::class, [
                'hasUrls' => true,
            ]),
            'handle' => 'volume',
        ]);

        Flux::getInstance()->set('s3', $this->make(S3::class, [
            'listObjects' => Expected::once(function () {
                return [
                    'Flux/volume/foo.webp',
                    'Flux/volume/foo.jpg',
                    'Flux/volume/bar.jpg',
                    'Flux/volume/_159x240_crop_center-center_80/foo.jpg',
                    'Flux/volume/_159x240_crop_center-center_80/foo.webp',
                    'Flux/volume/_159x240_crop_center-center_80/bar.jpg',
                ];
            }),
            'deleteObjects' => Expected::once(function ($objects) {
                $this->assertSame([
                    'Flux/volume/foo.webp',
                    'Flux/volume/foo.jpg',
                    'Flux/volume/bar.jpg',
                    'Flux/volume/_159x240_crop_center-center_80/foo.jpg',
                    'Flux/volume/_159x240_crop_center-center_80/foo.webp',
                    'Flux/volume/_159x240_crop_center-center_80/bar.jpg',
                ], $objects);
            })
        ]));

        Flux::getInstance()->s3->purgeAllTransformedVersions($volume);
    }

    public function testPurgesAllAssetsFromS3()
    {
        Flux::$plugin->settings->rootPrefix = "Flux";

        $volume = $this->make(Volume::class, [
            'getFs' => $this->make(AwsFs::class, [
                'hasUrls' => true,
            ]),
            'getTransformFs' => $this->make(Fs::class, [
                'hasUrls' => true,
            ]),
            'handle' => 'volume',
        ]);

        $asset = $this->make(Asset::class, [
            'getVolume' => $volume,
            'folderPath' => '/_actual/',
            'filename' => 'foo.jpg',
        ]);

        Flux::getInstance()->set('s3', $this->make(S3::class, [
            'listObjects' => Expected::once(function () {
                return [
                    'Flux/volume/foo.webp',
                    'Flux/volume/foo.jpg',
                    'Flux/volume/bar.jpg',
                    'Flux/volume/myfolder/_159x240_crop_center-center_80/bar.jpg',
                    'Flux/volume/_actual/foo.jpg',
                    'Flux/volume/_myfolder/another/bar.jpg',
                    'Flux/volume/_159x240_crop_center-center_80/foo.jpg',
                    'Flux/volume/_159x240_crop_center-center_80/foo.webp',
                    'Flux/volume/_159x240_crop_center-center_80/bar.jpg',
                ];
            }),
            'deleteObjects' => Expected::once(function ($objects) {
                $this->assertSame([
                    'Flux/volume/myfolder/_159x240_crop_center-center_80/bar.jpg',
                    'Flux/volume/_159x240_crop_center-center_80/foo.jpg',
                    'Flux/volume/_159x240_crop_center-center_80/foo.webp',
                    'Flux/volume/_159x240_crop_center-center_80/bar.jpg',
                ], $objects);
            })
        ]));

        Flux::getInstance()->s3->purgeAllTransformedVersions($volume, [$asset]);
    }
}
