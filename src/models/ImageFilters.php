<?php

namespace dyerc\flux\models;

use Craft;
use craft\base\Model;
use craft\helpers\ArrayHelper;

class ImageFilters extends Model
{
    public float|bool|null $blur = null;

    public ?float $brightness = null;

    public ?bool $greyscale = null;

    public ?float $hue = null;

    public ?float $lightness = null;

    public ?float $saturation = null;

    public ?array $tint = null;

    public function getConfig(): array
    {
        return [
            'blur' => $this->blur,
            'brightness' => $this->brightness,
            'greyscale' => $this->greyscale,
            'hue' => $this->hue,
            'lightness' => $this->lightness,
            'saturation' => $this->saturation,
            'tint' => $this->tint,
        ];
    }

    public static function normalize(mixed $filters): ?ImageFilters
    {
        if (!$filters) {
            return null;
        }

        if ($filters instanceof ImageFilters) {
            return $filters;
        }

        if (is_object($filters)) {
            $filters = ArrayHelper::toArray($filters);
        }

        if (is_array($filters)) {
            if (isset($filters['blur'])) {
                $blur = $filters['blur'];

                if (is_bool($blur) && $blur) {
                    $filters['blur'] = true;
                } elseif (is_numeric($blur) && $blur >= 0.3 && $blur <= 1000) {
                    $filters['blur'] = (float) $blur;
                } else {
                    $filters['blur'] = null;
                }
            }

            if (isset($filters['brightness'])) {
                $brightness = $filters['brightness'];
                if (is_numeric($brightness)) {
                    $filters['brightness'] = (float) round($brightness, 4);
                } else {
                    $filters['brightness'] = null;
                }
            }

            if (isset($filters['greyscale'])) {
                if (!is_bool($filters['greyscale'])) {
                    $filters['greyscale'] = null;
                }
            }

            if (isset($filters['hue'])) {
                $hue = $filters['hue'];
                if (is_numeric($hue)) {
                    $filters['hue'] = (float) round($hue, 4);
                } else {
                    $filters['hue'] = null;
                }
            }

            if (isset($filters['lightness'])) {
                $lightness = $filters['lightness'];
                if (is_numeric($lightness)) {
                    $filters['lightness'] = (float) round($lightness, 4);
                } else {
                    $filters['lightness'] = null;
                }
            }

            if (isset($filters['saturation'])) {
                $saturation = $filters['saturation'];
                if (is_numeric($saturation)) {
                    $filters['saturation'] = (float) round($saturation, 4);
                } else {
                    $filters['saturation'] = null;
                }
            }

            // Normalize tint: must be array with r, g, b keys or hex string beginning #
            if (isset($filters['tint'])) {
                if (is_string($filters['tint'])) {
                    [$r, $g, $b] = sscanf($filters['tint'], '#%02x%02x%02x');
                    $filters['tint'] = [
                        'r' => $r,
                        'g' => $g,
                        'b' => $b,
                    ];
                } elseif (!is_array($filters['tint']) ||
                    !isset($filters['tint']['r'], $filters['tint']['g'], $filters['tint']['b'])) {
                    $filters['tint'] = null;
                }
            }

            /** @var ImageFilters */
            return Craft::createObject([
                'class' => ImageFilters::class,
                ...$filters,
            ]);
        }

        return null;
    }
}
