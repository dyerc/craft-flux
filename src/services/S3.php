<?php
/**
 * @copyright Copyright (c) Chris Dyer
 */

namespace dyerc\flux\services;

use Aws\Lambda\Exception\LambdaException;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Craft;
use craft\elements\Asset;
use craft\helpers\App;
use craft\models\Volume;
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
                'region' => App::parseEnv($settings->awsRegion),
                'credentials' => [
                    'key' => App::parseEnv($settings->awsAccessKeyId),
                    'secret' => App::parseEnv($settings->awsSecretAccessKey)
                ]
            ]);
        }

        return $this->_client;
    }

    public function getStatus($roles = []): array|null
    {
        /* @var SettingsModel */
        $settings = Flux::getInstance()->getSettings();
        $bucket = App::parseEnv($settings->awsBucket);

        try {
            $response = $this->client()->getBucketPolicy([
                'Bucket' => $bucket
            ]);

            $policy = $response['Policy']->getContents();

            // Very basic naive check first
            $permissions = !empty($roles) && str_contains($policy, "s3:PutObject") && str_contains($policy, "s3:GetObject");

            // Ensure all roles are mentioned by the bucket policy
            foreach ($roles as $role) {
                if ($permissions && !str_contains($policy, $role)) {
                    $permissions = false;
                }
            }

            return [
                'available' => true,
                'permission' => $permissions,
                'bucket' => $bucket
            ];
        } catch (\Exception $e) {
            Craft::error($e->getMessage());
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
        $bucket = App::parseEnv($settings->awsBucket);

        try {
            $response = $this->client()->getBucketPolicy([
                'Bucket' => $bucket
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
                'Bucket' => $bucket,
                'Policy' => $encoded
            ]);
        }
    }

    public function listObjects(string $prefix, string|null $continue = null): array
    {
        /* @var SettingsModel */
        $settings = Flux::getInstance()->getSettings();

        $params = [
            'Bucket' => App::parseEnv($settings->awsBucket),
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
                $output = array_merge($output, $this->listObjects($prefix, $objects['NextContinuationToken']));
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
            'Bucket' => App::parseEnv($settings->awsBucket),
            'Delete' => [
                'Objects' => array_map(function ($key) {
                    return [ 'Key' => $key ];
                }, $paths),
                'Quiet' => true
            ]
        ]);
    }

    public function purgeTransformedVersions(Asset $asset)
    {
        /* @var SettingsModel */
        $settings = Flux::getInstance()->getSettings();
        $rootPrefix = App::parseEnv($settings->rootPrefix);

        $path = Flux::getInstance()->transformer->getPath($asset);
        if (strlen($rootPrefix) > 0) {
            $path = $rootPrefix . "/" . $path;
        }

        $prefix = pathinfo($path, PATHINFO_DIRNAME);
        $items = $this->listObjects($prefix);

        $deleteObjects = [];

        /*
         * Remove the file itself if it is a cached object and not the actual asset itself. Double check for S3 based filesystems
         */
        $originalPath = (!empty($asset->fs->subfolder) ? rtrim($asset->fs->subfolder, '/') . '/' : '') . $asset->getPath();

        if (!is_a($asset->volume->fs, "craft\\awss3\\Fs") && $originalPath != $path) {
            $deleteObjects[] = $path;
        }

        /*
            Match anything _transform/file.*
            It would be good to use a regex, but we won't because this could potentially iterate
            thousands of items
        */
        $fileName = pathinfo($asset->filename, PATHINFO_FILENAME);

        /*
         * Match any transformed files based on their path and file name
         */
        foreach ($items as $item) {
            $rel = substr($item, strlen($prefix) + 1); // +1 to remove first /

            $parent = dirname($rel);
            $baseName = pathinfo($rel, PATHINFO_FILENAME);

            /*
             * $parent will start with '_' if it is a transform folder
             * basename($rel) will be the filename
             */
            if (str_starts_with($parent, '_') && $fileName == $baseName) {
                $deleteObjects[] = $item;
            }
        }

        Craft::info(
            "Purging transformed versions of asset [" . $asset->id . "]: " . join(", ", $deleteObjects),
            __METHOD__
        );

        $this->deleteObjects($deleteObjects);
    }

    public function purgeAllTransformedVersions(Volume $volume, array $ignore = [])
    {
        /* @var SettingsModel */
        $settings = Flux::getInstance()->getSettings();
        $rootPrefix = App::parseEnv($settings->rootPrefix);

        $prefix = $volume->handle;

        if (strlen($rootPrefix) > 0) {
            $prefix = $rootPrefix . "/" . $prefix;
        }

        $items = $this->listObjects($prefix);
        $items = $this->filterAssetPaths($items, $ignore, $prefix);

        /*
         *  For non S3 filesystems, everything must be a Flux related file so wipe everything
         *  within the volume prefix. Otherwise only delete files that match our transform
         *  related pattern
         */
        if (is_a($volume->fs, "craft\\awss3\\Fs")) {
            $deleteObjects = [];

            // Remove all live assets from the list, just to be doubly sure nothing live can be deleted
            $items = $this->filterAssetPaths($items, Asset::find()->volume($volume)->all(), $prefix);

            foreach ($items as $item) {
                $rel = substr($item, strlen($prefix) + 1); // +1 to remove first /

                $parent = basename(dirname($rel));

                /*
                 * $parent will start with '_' if it is a transform folder
                 * basename($rel) will be the filename
                 */
                if (str_starts_with($parent, '_')) {
                    $deleteObjects[] = $item;
                }
            }
            $this->deleteObjects($deleteObjects);
        } else {
            $this->deleteObjects($items);
        }
    }

    /**
     * @param string[] $paths
     * @param Asset[] $needles
     * @param string $prefix
     * @return array
     */
    private function filterAssetPaths(array $paths, array $needles, string $prefix): array
    {
        $prefixedNeedles = array_map(function ($asset) use ($prefix) {
            return $prefix . $asset->path;
        }, $needles);

        return array_filter($paths, function ($item) use ($prefixedNeedles) {
            return !in_array($item, $prefixedNeedles);
        });
    }
}