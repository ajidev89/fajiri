<?php

namespace App\Http\Traits;

trait SluggableTrait
{
    protected static function bootSluggableTrait(): void
    {
        static::creating(function ($model) {
            $slugSource = $model->attributes[$model->sluggable['source']];
            $model->slug = $model->generateSlug($slugSource, $model->sluggable['source']);
        });
    }

    /**
     * @return array|string|string[]|null
     */
    private function generateSlug($key, $name): string|array|null
    {
        if (static::whereSlug($slug = str()->slug($key))->exists()) {
            $max = static::where("$name", '=', $key)->latest('id')->skip(1)->value('slug');
            if (isset($max[-1]) && is_numeric($max[-1])) {
                return preg_replace_callback('/(\d+)$/', function ($match) {
                    return $match[1] + 1;
                }, $max);
            } else {
                return "{$slug}-".+1;
            }
        }

        return $slug;
    }
}
