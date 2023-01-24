# Deployment

Flux is most likely a plugin you only want to have enabled on a production or staging environment. The caching CloudFront provides and environment specific setup of Flux means it isn't well suited to being used in a development environment.

## Local Development

If Flux is installed but disabled in the plugin Settings, your Craft site will fall back to using the built in [Image Transforms](https://craftcms.com/docs/4.x/image-transforms.html). It is recommended to configure this by creating a file `flux.php` in your site `config/` folder. This file can be used to override any Flux settings in the [normal manner](https://craftcms.com/docs/4.x/extend/plugin-settings.html#overriding-setting-values).

```php
<?php

return [
  '*' => [
    'enabled' => false,
  ],

  'production' => [
    'enabled' => true,
  ]
];
```

This will keep Flux installed, but disable it in all environments except for production

## Deployment Process

Flux is designed to be installed to AWS whenever you amend Flux settings or update the plugin. You can check on the status of Flux's installation, purge the cache or install it through the Utilities section of your Craft admin area.

You can automate the process of reinstalling Flux if necessary within your deployment process by running the following console command:

```shell
php craft flux/aws/update
```

If the Flux installation live on AWS is an outdated version or contains an outdated configuration, Flux will be reinstalled. To reinstall Flux from the console you can use the `flux/aws/install` command which will not check the current status or config.
