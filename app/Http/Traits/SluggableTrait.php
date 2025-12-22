<?php

namespace App\Http\Traits;

trait SluggableTrait
{
    protected static function bootSluggableTrait(): void
    {
        static::creating(function ($model) {
            $source = $model->sluggable['source'];
            $value = $model->$source;

            $model->slug = $model->generateSlug($value);
        });
    }

    private function generateSlug(string $value): string
    {
        $slug = str()->slug($value);
        $original = $slug;
        $count = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = "{$original}-{$count}";
            $count++;
        }

        return $slug;
    }
}
