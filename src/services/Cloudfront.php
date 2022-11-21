<?php
/**
 * @copyright Copyright (c) Chris Dyer
 */

namespace dyerc\flux\services;

use Aws\CloudFront\CloudFrontClient;
use Aws\Lambda\Exception\LambdaException;
use craft\elements\Asset;
use craft\helpers\App;
use dyerc\flux\Flux;
use dyerc\flux\models\SettingsModel;
use yii\base\Component;

class Cloudfront extends Component
{
    private CloudFrontClient|null $_client = null;

    public function client(?string $keyId = null, ?string $secret = null): CloudFrontClient
    {
        if (!$this->_client) {
            /* @var SettingsModel */
            $settings = Flux::getInstance()->getSettings();

            $this->_client = new CloudFrontClient([
                'version' => 'latest',
                'region' => $settings->awsRegion,
                'credentials' => [
                    'key' => App::parseEnv($settings->awsAccessKeyId),
                    'secret' => App::parseEnv($settings->awsSecretAccessKey)
                ]
            ]);
        }

        return $this->_client;
    }

    public function getDistributionId(): string
    {
        /* @var SettingsModel */
        $settings = Flux::getInstance()->getSettings();
        return $settings->cloudFrontDistributionId;
    }

    public function getDistributions(): array
    {
        $objects = $this->client()->listDistributions();

        if (empty($objects['DistributionList']['Items'])) {
            return [];
        }

        $distributions = $objects['DistributionList']['Items'];
        $distributionList = [];

        foreach ($distributions as $distribution) {
            $distributionList[] = [
                'id' => $distribution['Id'],
                'domain' => $distribution['DomainName']
            ];
        }

        return $distributionList;
    }

    public function getStatus(): array|null
    {
        try {
            $distribution = $this->client()->getDistribution([
                'Id' => $this->getDistributionId()
            ]);

            return [
                'id' => $distribution['Distribution']['Id'],
                'installed' => $distribution['Distribution']['DistributionConfig']['DefaultCacheBehavior']['LambdaFunctionAssociations']['Quantity'] == 2,
                'domain' => $distribution['Distribution']['DomainName']
            ];
        } catch (LambdaException $e) {
            return null;
        }
    }

    // Load a cache policy from its name
    public function getCachePolicy(string $name): \Aws\Result|null
    {
        $results = $this->client()->listCachePolicies([]);

        if ($results['CachePolicyList']['Items']) {
            foreach ($results['CachePolicyList']['Items'] as $item) {
                if ($item['CachePolicy']['CachePolicyConfig']['Name'] == $name) {
                    // We've found the ID, now query the actual item
                    return $this->client()->getCachePolicy([
                        'Id' => $item['CachePolicy']['Id']
                    ]);
                }
            }
        }

        return null;
    }

    // Load an origin request policy from its name
    public function getOriginRequestPolicy(string $name): \Aws\Result|null
    {
        $results = $this->client()->listOriginRequestPolicies([]);

        if ($results['OriginRequestPolicyList']['Items']) {
            foreach ($results['OriginRequestPolicyList']['Items'] as $item) {
                if ($item['OriginRequestPolicy']['OriginRequestPolicyConfig']['Name'] == $name) {
                    // We've found the ID, now query the actual item
                    return $this->client()->getOriginRequestPolicy([
                        'Id' => $item['OriginRequestPolicy']['Id']
                    ]);
                }
            }
        }

        return null;
    }

    public function updateCachePolicy(string $name, array $overrides = []): string
    {
        $config = array_merge([
            'Name' => $name,
            'MinTTL' => 1,
            'MaxTTL' => 31536000,
            'DefaultTTL' => 86400,
            'ParametersInCacheKeyAndForwardedToOrigin' => [
                'CookiesConfig' => [
                    'CookieBehavior' => 'none',
                ],
                'EnableAcceptEncodingBrotli' => true,
                'EnableAcceptEncodingGzip' => true,
                'HeadersConfig' => [
                    'HeaderBehavior' => 'none',
                ],
                'QueryStringsConfig' => [
                    'QueryStringBehavior' => 'all',
                ],
            ]
        ], $overrides);

        $policy = $this->getCachePolicy($name);

        if ($policy) {
            $updated = $this->client()->updateCachePolicy([
                'CachePolicyConfig' => $config,
                'Id' => $policy['CachePolicy']['Id'],
                'IfMatch' => $policy['ETag']
            ]);

            return $updated['CachePolicy']['Id'];
        } else {
            $created = $this->client()->createCachePolicy([
                'CachePolicyConfig' => $config
            ]);

            return $created['CachePolicy']['Id'];
        }
    }

    public function updateOriginRequestPolicy(string $name, array $overrides = [])
    {
        $config = array_merge([
            'Name' => $name,
            'CookiesConfig' => [
                'CookieBehavior' => 'none',
            ],
            'HeadersConfig' => [
                'HeaderBehavior' => 'whitelist',
                'Headers' => [
                    'Items' => ['X-Flux-Source-Filename'],
                    'Quantity' => 1
                ]
            ],
            'QueryStringsConfig' => [
                'QueryStringBehavior' => 'all',
            ],
        ], $overrides);

        $policy = $this->getOriginRequestPolicy($name);

        if ($policy) {
            $updated = $this->client()->updateOriginRequestPolicy([
                'OriginRequestPolicyConfig' => $config,
                'Id' => $policy['OriginRequestPolicy']['Id'],
                'IfMatch' => $policy['ETag']
            ]);

            return $updated['OriginRequestPolicy']['Id'];
        } else {
            $created = $this->client()->createOriginRequestPolicy([
                'OriginRequestPolicyConfig' => $config
            ]);

            return $created['OriginRequestPolicy']['Id'];
        }
    }

    public function invalidateCache(array $paths = [])
    {
        /* @var SettingsModel */
        $settings = Flux::getInstance()->getSettings();

        if (empty($paths)) {
            $paths = [ "/*" ];
        }

        $this->client()->createInvalidation([
            'DistributionId' => $settings->cloudFrontDistributionId,
            'InvalidationBatch' => [
                'CallerReference' => $settings->awsResourcePrefix . "-" . time(),
                'Paths' => [
                    'Items' => $paths,
                    'Quantity' => count($paths)
                ]
            ]
        ]);
    }

    public function purgeAsset(Asset $asset)
    {
        /* @var SettingsModel */
        $settings = Flux::getInstance()->getSettings();

        $path = Flux::getInstance()->transformer->getPath($asset);
        if (strlen($settings->rootPrefix) > 0) {
            $path = $settings->rootPrefix . "/" . $path;
        }

        $prefix = pathinfo($path, PATHINFO_DIRNAME);
        $items = Flux::getInstance()->s3->listObjects($prefix);

        $purgePaths = [$path];
        $purgeObjects = [];

        /*
         * Remove the file itself if it is a cached object and not the actual asset itself
         */
        if (!is_a($asset->volume->fs, "craft\\awss3\\Fs")) {
            $purgeObjects[] = $path;
        }

        /*
            Match anything _transform/file.*
            It would be good to use a regex, but we won't because this could potentially iterate
            thousands of items
        */
        $fileName = pathinfo($asset->filename, PATHINFO_FILENAME);

        foreach ($items as $item) {
            $rel = substr($item, strlen($prefix) + 1); // +1 to remove first /
            $chunks = explode('/', $rel);
            if (count($chunks) == 2 && strlen($chunks[0]) > 0 && $chunks[0][0] == '_') {
                // Split off the last . to retrieve the file extension
                $parts = explode('.', $chunks[1]);
                $ext = array_pop($parts);
                $parts = array(implode('.', $parts), $ext);

                if ($parts[0] == $fileName) {
                    // $purgePaths[] = $item;
                    $purgeObjects[] = $item;
                }
            }
        }

        $this->invalidateCache(array_map(function ($path) use ($settings) {
            if (str_starts_with($path, $settings->rootPrefix)) {
                return substr($path, strlen($settings->rootPrefix));
            } else {
                return $path;
            }
        }, $purgePaths));
        Flux::getInstance()->s3->deleteObjects($purgeObjects);
    }
}