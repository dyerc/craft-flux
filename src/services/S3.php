<?php
/**
 * @copyright Copyright (c) Chris Dyer
 */

namespace dyerc\flux\services;

use Aws\Lambda\Exception\LambdaException;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Craft;
use craft\helpers\App;
use dyerc\flux\Flux;
use dyerc\flux\helpers\PolicyHelper;
use dyerc\flux\models\SettingsModel;
use yii\base\Component;

class S3 extends Component
{
    private S3Client|null $_client = null;

    public function client(): S3Client
    {
        if (!$this->_client) {
            /* @var SettingsModel */
            $settings = Flux::getInstance()->getSettings();

            $this->_client = new S3Client([
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

    public function getStatus(): array|null
    {
        /* @var SettingsModel */
        $settings = Flux::getInstance()->getSettings();

        try {
            $response = $this->client()->getBucketPolicy([
                'Bucket' => $settings->awsBucket
            ]);

            $policy = $response['Policy']->getContents();
            $permissions = str_contains($policy, ":role/" . $settings->awsResourcePrefix)
                && str_contains($policy, "s3:PutObject")
                && str_contains($policy, "arn:aws:s3:::" . $settings->awsBucket);

            return [
                'available' => true,
                'permission' => $permissions,
                'bucket' => $settings->awsBucket
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getBuckets(): array
    {
        $objects = $this->client()->listBuckets();

        if (empty($objects['Buckets'])) {
            return [];
        }

        $buckets = $objects['Buckets'];
        $bucketList = [];

        foreach ($buckets as $bucket) {
            try {
                $region = $this->client()->determineBucketRegion($bucket['Name']);
            } catch (S3Exception $exception) {

                // If a bucket cannot be accessed by the current policy, move along:
                // https://github.com/craftcms/aws-s3/pull/29#issuecomment-468193410
                continue;
            }

            $bucketList[] = [
                'bucket' => $bucket['Name'],
                'region' => $region,
            ];
        }

        return $bucketList;
    }

    public function checkAndFixBucketPolicy(array $functionRoles): void
    {
        /* @var SettingsModel */
        $settings = Flux::getInstance()->getSettings();
        $master = PolicyHelper::bucketPolicy($functionRoles);

        try {
            $response = $this->client()->getBucketPolicy([
                'Bucket' => $settings->awsBucket
            ]);

            $policy = json_decode($response['Policy']->getContents(), true);
        } catch (S3Exception $exception) {
            // Probably because the policy doesn't exist
            $policy = [];
        }

        $updateRequired = false;

        if (array_key_exists('Statement', $policy) && is_array($policy['Statement'])) {
            $additions = [];

            foreach ($master['Statement'] as $rule) {
                // Does this master rule exist in the bucket policy
                $matches = array_filter($policy['Statement'], function ($s) use ($rule) {
                    return $s['Effect'] == $rule['Effect'] && $s['Principal'] == $rule['Principal'] && $s['Action'] == $rule['Action'] && $s['Resource'] == $rule['Resource'];
                });

                // This rule needs to be added
                if (empty($matches)) {
                    $additions[] = $rule;
                }
            }

            if (!empty($additions)) {
                $policy['Statement'] = array_merge($policy['Statement'], $additions);
                Craft::info("Adding new items to bucket policy.");
                $updateRequired = true;
            }
        } else {
            $policy = $master;
            $updateRequired = true;
        }

        if ($updateRequired) {
            $encoded = json_encode($policy);

            $this->client()->putBucketPolicy([
                'Bucket' => $settings->awsBucket,
                'Policy' => $encoded
            ]);
        }
    }

    public function listObjects(string $prefix, string|null $continue = null): array
    {
        /* @var SettingsModel */
        $settings = Flux::getInstance()->getSettings();

        $params = [
            'Bucket' => $settings->awsBucket,
            'Prefix' => $prefix,
            'MaxKeys' => 1000,
        ];

        if ($continue) {
            $params['ContinuationToken'] = $continue;
        }

        $objects = Flux::getInstance()->s3->client()->listObjectsV2($params);

        if ($objects['Contents']) {
            $output = array_map(function ($item) use ($prefix) {
                return $item['Key'];
            }, $objects['Contents']);

            // Recursively fetch more results
            if ($objects['NextContinuationToken']) {
                $output = array_merge($output, $this->listObjectsPrefixed($prefix, $objects['NextContinuationToken']));
            }

            return $output;
        } else {
            return [];
        }
    }

    public function deleteObjects(array $paths): void
    {
        /* @var SettingsModel */
        $settings = Flux::getInstance()->getSettings();

        $this->client()->deleteObjects([
            'Bucket' => $settings->awsBucket,
            'Delete' => [
                'Objects' => array_map(function ($key) {
                    return [ 'Key' => $key ];
                }, $paths),
                'Quiet' => true
            ]
        ]);
    }
}