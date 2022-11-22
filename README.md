[![Stable Version](https://img.shields.io/packagist/v/dyerc/craft-flux?label=stable)]((https://packagist.org/packages/dyerc/craft-flux))
[![Total Downloads](https://img.shields.io/packagist/dt/dyerc/craft-flux)](https://packagist.org/packages/dyerc/craft-flux)

<p align="center" style="margin-bottom: 16px;"><img width="130" src="https://raw.githubusercontent.com/dyerc/craft-flux/master/src/icon.svg"></p>

# Flux Plugin for Craft CMS

Flux integrates your site image transforms with AWS, using Lambda and CloudFront to process, cache and serve your images.

- Image heavy pages will feel more instantaneous to load because much faster CloudFront servers perform the image transformation operations
- Process image transforms on demand, only when they are requested by the user
- Supports all filesystems including local folders
- Automatically purge and invalidate CloudFront when assets change

## Documentation

Learn more and read the documentation at [cdyer.co.uk/plugins/flux »](https://cdyer.co.uk/plugins/flux).

## License

This plugin requires a commercial license purchasable through the [Craft Plugin Store](https://plugins.craftcms.com/flux).

## Requirements

This plugin requires [Craft CMS](https://craftcms.com/) 4.0.0 or later.

## Installation

To install the plugin, search for “Flux” in the Craft Plugin Store, or install manually using composer.

```shell
composer require dyerc/craft-flux
```

---

Created by [Chris Dyer](https://cdyer.co.uk).
