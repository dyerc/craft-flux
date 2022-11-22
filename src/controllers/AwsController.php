<?php
/**
 * @copyright Copyright (c) Chris Dyer
 */

namespace dyerc\flux\controllers;

use Aws\CloudFront\CloudFrontClient;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Craft;
use craft\helpers\App;
use craft\helpers\Queue;
use craft\web\Controller;
use dyerc\flux\helpers\PolicyHelper;
use dyerc\flux\jobs\InvalidateCloudFrontJob;
use dyerc\flux\jobs\LambdaDeployJob;
use yii\web\Response;

class AwsController extends Controller
{
    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        $this->defaultAction = 'load-bucket-data';
    }

    public function actionBuildBucketPolicy(): Response
    {
        $this->requireAdmin();
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();
        $bucket = App::parseEnv($request->getRequiredBodyParam('bucket'));
        $root = App::parseEnv($request->getRequiredBodyParam('root'));

        return $this->asRaw(
            PolicyHelper::prettyPrint(PolicyHelper::iamUserPolicy($bucket, $root))
        );
    }

    public function actionUpdateCodeBundle(): Response
    {
        $this->requirePostRequest();

        Queue::push(new LambdaDeployJob());

        $notice = Craft::t('flux', 'Installing AWS Lambda functions');
        Craft::$app->getSession()->setNotice($notice);

        return $this->redirectToPostedUrl();
    }

    public function actionInvalidateCloudfront(): Response
    {
        $this->requirePostRequest();

        Queue::push(new InvalidateCloudFrontJob());

        $notice = Craft::t('flux', 'Purging CloudFront cache');
        Craft::$app->getSession()->setNotice($notice);

        return $this->redirectToPostedUrl();
    }

    /**
     * Load bucket data for specified credentials.
     *
     * @return Response
     */
    public function actionLoadBucketData(): Response
    {
        $this->requireAdmin();
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();
        $keyId = App::parseEnv($request->getRequiredBodyParam('keyId'));
        $secret = App::parseEnv($request->getRequiredBodyParam('secret'));

        try {
            $buckets = $this->loadBucketList($keyId, $secret);

            return $this->asJson([
                'buckets' => $buckets
            ]);
        } catch (\Throwable $e) {
            return $this->asFailure($e->getMessage());
        }
    }

    /**
     * Load distributions data for specified credentials.
     *
     * @return Response
     */
    public function actionLoadDistributionsData(): Response
    {
        $this->requireAdmin();
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();
        $keyId = App::parseEnv($request->getRequiredBodyParam('keyId'));
        $secret = App::parseEnv($request->getRequiredBodyParam('secret'));

        try {
            $buckets = $this->loadCloudFrontDistributions($keyId, $secret);

            return $this->asJson([
                'distributions' => $buckets
            ]);
        } catch (\Throwable $e) {
            return $this->asFailure($e->getMessage());
        }
    }

    private function loadBucketList(?string $keyId, ?string $secret)
    {
        $client = new S3Client([
            'version' => 'latest',
            'region' => 'us-east-1',
            'credentials' => [
                'key' => $keyId,
                'secret' => $secret
            ]
        ]);

        $objects = $client->listBuckets();

        if (empty($objects['Buckets'])) {
            return [];
        }

        $buckets = $objects['Buckets'];

        $bucketList = [];

        foreach ($buckets as $bucket) {
            try {
                $region = $client->determineBucketRegion($bucket['Name']);
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

    private function loadCloudFrontDistributions(?string $keyId, ?string $secret)
    {
        $client = new CloudFrontClient([
            'version' => 'latest',
            'region' => 'us-east-1',
            'credentials' => [
                'key' => $keyId,
                'secret' => $secret
            ]
        ]);

        $objects = $client->listDistributions();

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
}