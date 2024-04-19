<?php
/**
 * @copyright Copyright (c) Chris Dyer
 */

namespace dyerc\flux\services;

use Aws\CloudFront\CloudFrontClient;
use Aws\CloudFront\Exception\CloudFrontException;
use craft\helpers\App;
use dyerc\flux\Flux;
use dyerc\flux\models\SettingsModel;
use yii\base\Component;

class Cloudfront extends Component
{
    public const GLOBAL_CACHE_POLICY_NAME = 'Flux-Cache-Policy';

    public const GLOBAL_ORIGIN_REQUEST_POLICY_NAME = 'Flux-Origin-Request-Policy';

    private CloudFrontClient|null $_client = null;

    public function client(?string $keyId = null, ?string $secret = null): CloudFrontClient
    {
        if (!$this->_client) {
            /* @var SettingsModel $settings */
            $settings = Flux::getInstance()->getSettings();

            $this->_client = new CloudFrontClient([
                'version' => 'latest',
                'region' => App::parseEnv($settings->awsRegion),
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
        /* @var SettingsModel $settings */
        $settings = Flux::getInstance()->getSettings();
        return App::parseEnv($settings->cloudFrontDistributionId);
    }

    public function getDistributionArn(): string
    {
        try {
            $distribution = $this->client()->getDistribution([
              'Id' => $this->getDistributionId()
            ]);

            return $distribution['Distribution']['ARN'];
        } catch (CloudFrontException $e) {
            return "";
        }
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
        } catch (CloudFrontException $e) {
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

    public function updateCachePolicy(array $overrides = []): string
    {
        /* @var SettingsModel $settings */
        $settings = Flux::getInstance()->getSettings();

        if ($settings->awsStrictNaming) {
            $prefix = App::parseEnv($settings->awsResourcePrefix);
            $name = "$prefix-Cache-Policy";
        } else {
            $name = self::GLOBAL_CACHE_POLICY_NAME;
        }

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

    public function updateOriginRequestPolicy(array $overrides = [])
    {
        /* @var SettingsModel $settings */
        $settings = Flux::getInstance()->getSettings();

        if ($settings->awsStrictNaming) {
            $prefix = App::parseEnv($settings->awsResourcePrefix);
            $name = "$prefix-Origin-Request-Policy";
        } else {
            $name = self::GLOBAL_ORIGIN_REQUEST_POLICY_NAME;
        }

        $config = array_merge([
            'Name' => $name,
            'CookiesConfig' => [
                'CookieBehavior' => 'none',
            ],
            'HeadersConfig' => [
                'HeaderBehavior' => 'whitelist',
                'Headers' => [
                    'Items' => ['X-Flux-Source-Filename', 'X-Flux-Original-Request'],
                    'Quantity' => 2
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

    public function invalidateCache(array $paths = [], int $chunkSize = 3000)
    {
        /* @var SettingsModel $settings */
        $settings = Flux::getInstance()->getSettings();

        if (empty($paths)) {
            $paths = [ "/*" ];
        }

        $batches = array_chunk($paths, $chunkSize);

        foreach ($batches as $batch) {
            $this->client()->createInvalidation([
                'DistributionId' => App::parseEnv($settings->cloudFrontDistributionId),
                'InvalidationBatch' => [
                    'CallerReference' => App::parseEnv($settings->awsResourcePrefix) . "-" . time(),
                    'Paths' => [
                        'Items' => $batch,
                        'Quantity' => count($batch)
                    ]
                ]
            ]);

            if (count($batches) > 1) {
                sleep(1);
            }
        }
    }
}