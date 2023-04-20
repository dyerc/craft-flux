# Flux Release Notes

## 4.1.5 - 2023-04-20

- Fix an S3 error when attempting to purge an empty array of transformed assets ([#6](https://github.com/dyerc/craft-flux/issues/6))

## 4.1.4 - 2023-04-11

- Fix settings validation when using environment variables ([#5](https://github.com/dyerc/craft-flux/issues/5))

## 4.1.3 - 2023-03-22

- Fix an issue ensuring that Lambda reads and writes assets in the correct AWS region

## 4.1.2 - 2023-01-24

- Fix an issue in multi-site Craft installs

## 4.1.1 - 2022-12-15

- By default, logging to CloudWatch is now disabled
- Fixed error installing to AWS when logging is disabled
- `flux/aws/update` console command will now check version and configuration before reinstalling if necessary
- Fixed issue with retrieving CloudFront status

## 4.1.0 - 2022-12-14

- Added asset focal point support
- Added cache parameter, removing reliance on CloudFront invalidations when an asset is changed
- Added action to purge all transformed files from utility screen
- If a Lambda update is available, Flux will now display the target version
- Fixed issue with purging outdated transformed files
- Fixed issue with congruency of transform results between Craft and Flux

## 4.0.1 - 2022-12-01

- Added additional verification that currently installed AWS configuration matches current Craft settings
- Added additional logging if a Craft volume can't be determined
- Fixed issue installing to AWS due to insufficient iam credentials ([#2](https://github.com/dyerc/craft-flux/issues/2))

## 4.0.0 - 2022-11-23

- Initial release.
