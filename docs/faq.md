# FAQ

## How expensive is Flux to run?

Explaining AWS pricing is way beyond the scope of this FAQ section, however the essential points are:

- All charges are proportional to the number of images being served
- S3 charge for transformed asset storage
- Lambda charges for redirecting to cached resources and processing an image

#### Image Serving (Viewer Request Function)

*Below are example approximate Lambda costs assuming a 128MB memory allocation. These are purely to give you a rough idea.*
| Request Count | Billed Duration | Price       |
| ------------- | --------------- | ----------- |
| 1             |    ~ 40ms       |  $0.0000016 |
| 10K           |    ~ 40ms       |  $0.017     |
| 1M            |    ~ 40ms       |  $2.40      |

#### Image Processing (Origin Response Function)

*Below are example approximate Lambda costs assuming a 512MB memory allocation. These are purely to give you a rough idea.*
| Output Image Size | Billed Duration | Price     |
| ----------------- | --------------- | --------- |
| Very Small  ~70px |           360ms | $0.000009 |
| Small   ~500px    |           500ms | $0.000012 |

## Uninstalling Flux

Flux can be installed as a Craft plugin in the normal way, either through **Settings** → **Plugins** or using Composer. However, to completely remove it from your AWS account, you will need to manually check for an remove the following resources from your AWS console. You should be able to spot them because they will be prefixed with the **AWS Resource Prefix** you selected when installing Flux.

- Remove 2x Lambda functions from the `us-east-1` region
- Delete the CloudFront distribution you created when installing Flux
- Delete the S3 bucket you created when installing Flux
- Delete the IAM user you created when installing Flux
- Remove any logs from CloudWatch

