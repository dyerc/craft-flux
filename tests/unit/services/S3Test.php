<?php
/**
 * @copyright Copyright (c) Chris Dyer
 */

namespace dyerc\fluxtests\unit\services;

use Aws\S3\S3Client;
use Codeception\Stub;
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

    public function testBatchesUpDeleteObjectsRequests()
    {
        $request = 0;
        $test = $this;

        $mockClient = $this->getMockBuilder(S3Client::class)
          ->disableOriginalConstructor()
          ->addMethods(['deleteObjects'])
          ->getMock();

        $mockClient->expects($this->exactly(2))
            ->method('deleteObjects')
            ->will($this->returnCallback(
                function ($params) use ($test, &$request) {
                    $request += 1;
                    $objects = $params['Delete']['Objects'];

                    if ($request == 1) {
                        $test->assertSame([[ 'Key' => "1.jpg" ], [ 'Key' => "2.jpg" ]], $objects);
                    } elseif ($request == 2) {
                        $test->assertSame([[ 'Key' => "3.jpg" ], [ 'Key' => "4.jpg" ]], $objects);
                    }
                }
            ));

        Flux::getInstance()->set('s3', $this->make(S3::class, [
            'client' => function() use ($mockClient) {
                return $mockClient;
            }
        ]));

        Flux::getInstance()->s3->deleteObjects(["1.jpg", "2.jpg", "3.jpg", "4.jpg"], 2);
    }
}
