# Helpers & Utilities

## LQIP (Low-Quality Image Placeholder)

`craft.flux.lqip(asset)`

Helper function for creating a low quality image placeholder (LQIP) of the image. By default creates an image 1/4 of the full size image with a gaussian blur of 25 so that the image doesn't look pixelated.

```twig
<img
  src="{{ craft.flux.lqip(asset) }}"
  srcset="
    {{ asset.getUrl({ mode: 'fit', width: 400 }) }} 400w,
    {{ asset.getUrl({ mode: 'fit', width: 600 }) }} 600w
    {# more image size transformed urls #}
  "
  sizes="100vw"
  width="{{ asset.width }}"
  height="{{ asset.height }}"
  loading="lazy"
/>
```

Customize the image size ratio and blur factor in your `flux.php` settings file.
