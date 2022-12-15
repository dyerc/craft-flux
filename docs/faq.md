# FAQ

## How expensive is Flux to run?

Flux will incur AWS usage costs for the following services:

- **Lambda + Lambda@Edge**: request processing
- **S3**: transformed asset storage
- **CloudFront**: bandwidth serving images
- **CloudWatch**: logging (only if enabled)

It is difficult to estimate costs because all charges are related to your unique usage characteristics. For instance: 

- Number of images being served
- Complexity of transform operations
- How many requests are for existing image variations vs new variations being processed

In real-world situations, CloudFront bandwidth is most likely to be the largest expense, compared to S3 or Lambda charges.

#### Lambda Image Serving (Viewer Request Function)

Viewer requests are often processed in as fast as 3ms, although sometimes they can take as long as 180ms. I haven't yet found a discernible reason for the variance other than CloudFront/Lambda resource contention. The 40ms estimate below is used as a pessimistic average. 

*Below are example approximate Lambda costs assuming a 128MB memory allocation. These are purely to give you a rough idea.*
| Request Count | Billed Duration | Price       |
| ------------- | --------------- | ----------- |
| 1             |    ~ 40ms       |  $0.0000016 |
| 10K           |    ~ 40ms       |  $0.017     |
| 1M            |    ~ 40ms       |  $2.40      |

#### Lambda Image Processing (Origin Response Function)

*Below are example approximate Lambda costs assuming a 512MB memory allocation. These are purely to give you a rough idea.*
| Output Image Size | Billed Duration | Price     |
| ----------------- | --------------- | --------- |
| Very Small  ~70px |           360ms | $0.000009 |
| Small   ~500px    |           500ms | $0.000012 |

## Uninstalling Flux

Flux can be installed as a Craft plugin in the normal way, either through **Settings** → **Plugins** or using Composer. However, to completely remove it from your AWS account, you will need to manually check for and remove the following resources from your AWS console. You should be able to spot them because they will be prefixed with the **AWS Resource Prefix** you selected when installing Flux.

1. Delete the CloudFront distribution you created when installing Flux
2. Delete 2x Lambda functions from the `us-east-1` region
3. Delete the S3 bucket you created when installing Flux. If Flux shared this bucket with other applications or Flux installations, edit the bucket policy to remove any *Statement* sections where *Principal* → *AWS* contains your AWS resource prefix.
4. Remove any logs from CloudWatch
5. Delete the IAM user you created when installing Flux
6. In IAM → Roles, delete 2x roles beginning with your prefix and ending `-Viewer-Request-Role` + `-Origin-Response-Role`

Any trace of Flux should now be removed from your AWS account.
