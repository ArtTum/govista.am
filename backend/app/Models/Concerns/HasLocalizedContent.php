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
                $translations = array_filter($value, fn ($translation) => $translation !== null && $translation !== '' && $translation !== []);
                $data[$field] = $translations[$locale] ?? $translations['hy'] ?? $translations['en'] ?? (reset($translations) ?: (in_array($field, ['highlights', 'itinerary', 'included', 'excluded', 'features'], true) ? [] : ''));
            }
        }

        return $data;
    }
}
