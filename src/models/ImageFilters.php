<?php

namespace dyerc\flux\models;

use Craft;
use craft\base\Model;
use craft\helpers\ArrayHelper;

class ImageFilters extends Model
{
    public float|bool|null $blur = null;

    public ?bool $greyscale = null;

    public ?array $tint = null;

    public function getConfig(): array
    {
        return [
            'blur' => $this->blur,
            'tint' => $this->tint,
            'greyscale' => $this->greyscale,
        ];
    }

    public static function normalize(mixed $filters): ?ImageFilters
    {
        if (! $filters) {
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

            if (isset($filters['greyscale'])) {
                if (! is_bool($filters['greyscale'])) {
                    $filters['greyscale'] = null;
                }
            }

            // Normalize tint: must be array with r, g, b keys
            if (isset($filters['tint'])) {
                if (is_string($filters['tint'])) {
                    [$r, $g, $b] = sscanf($filters['tint'], '#%02x%02x%02x');
                    $filters['tint'] = [
                        'r' => $r,
                        'g' => $g,
                        'b' => $b,
                    ];
                } elseif (! is_array($filters['tint']) ||
                    ! isset($filters['tint']['r'], $filters['tint']['g'], $filters['tint']['b'])) {
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
