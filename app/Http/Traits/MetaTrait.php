<?php

namespace App\Http\Traits;

trait MetaTrait
{
    public function updateMeta($data = [])
    {
        $meta = (array) $this->meta;
        $meta = array_merge($meta, $data);

        $this->meta = (object) $meta;
        $this->save();

        return $this;
    }

    public function updateMetaKey($key, $value)
    {
        $meta = (array) $this->meta;

        $meta[$key] = $value;
        $this->meta = (object) $meta;
        $this->save();

        return $this;
    }

    public function getMetaKey($key, $default = null)
    {
        $meta = (array) $this->meta;

        return $meta[$key] ?? $default;
    }
}
