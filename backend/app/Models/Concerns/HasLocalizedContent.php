<?php

namespace App\Models\Concerns;

trait HasLocalizedContent
{
    public function toLocalizedArray(string $locale = 'hy'): array
    {
        $data = $this->toArray();

        foreach ($this->translatable ?? [] as $field) {
            $value = $this->getAttribute($field);

            if (is_array($value)) {
                $data[$field] = $value[$locale] ?? $value['hy'] ?? $value['en'] ?? reset($value);
            }
        }

        return $data;
    }
}
