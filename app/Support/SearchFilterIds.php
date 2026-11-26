<?php

namespace App\Support;

final class SearchFilterIds
{
    /** Optional query lists may arrive as JSON strings or ordinary arrays. */
    public static function normalize($value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_map('intval', array_filter($value, function ($id) {
            return (is_int($id) || is_string($id)) && ctype_digit((string) $id) && (int) $id > 0;
        }))));
    }
}
