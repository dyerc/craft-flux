<?php
/**
 * @copyright Copyright (c) Chris Dyer
 */

namespace dyerc\fluxtests\unit\services;

use Aws\CloudFront\CloudFrontClient;

use craft\test\TestCase;
use dyerc\flux\Flux;
use dyerc\flux\services\Cloudfront;
use UnitTester;

class CloudfrontTest extends TestCase
{
    /**
     * @var UnitTester
     */
    protected $tester;

    public function testBatchesUpInvalidationRequests()
    {
        $request = 0;
        $test = $this;

        $mockClient = $this->getMockBuilder(CloudFrontClient::class)
          ->disableOriginalConstructor()
          ->addMethods(['createInvalidation'])
          ->getMock();

        $mockClient->expects($this->exactly(2))
          ->method('createInvalidation')
          ->will($this->returnCallback(
            function ($params) use ($test, &$request) {
                $request += 1;
                $objects = $params['InvalidationBatch']['Paths']['Items'];

                if ($request == 1) {
                    $test->assertSame(["1.jpg", "2.jpg"], $objects);
                } elseif ($request == 2) {
                    $test->assertSame(["3.jpg", "4.jpg"], $objects);
                }
            }
          ));

        Flux::getInstance()->set('cloudfront', $this->make(Cloudfront::class, [
          'client' => function() use ($mockClient) {
              return $mockClient;
          }
        ]));

        Flux::getInstance()->cloudfront->invalidateCache(["1.jpg", "2.jpg", "3.jpg", "4.jpg"], 2);
    }
}