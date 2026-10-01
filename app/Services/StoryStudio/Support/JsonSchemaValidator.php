<?php

namespace App\Services\StoryStudio\Support;

/**
 * Minimal JSON Schema validator for the subset used by Story Studio schemas:
 * type (incl. union with "null"), properties, required, additionalProperties=false,
 * items, enum, minItems, maxItems, minimum, maximum, minLength.
 */
class JsonSchemaValidator
{
    /**
     * @param  array<string, mixed>  $schema
     * @return list<string> errors (empty when valid)
     */
    public function validate(mixed $data, array $schema, string $path = '$'): array
    {
        $errors = [];
        $types = (array) ($schema['type'] ?? []);

        if ($types !== [] && ! $this->matchesType($data, $types)) {
            return [$path.': expected '.implode('|', $types).', got '.get_debug_type($data)];
        }

        if ($data === null) {
            return [];
        }

        if (isset($schema['enum']) && ! in_array($data, $schema['enum'], true)) {
            $errors[] = $path.': value not in enum ['.implode(', ', array_map('strval', $schema['enum'])).']';
        }

        if (is_string($data) && isset($schema['minLength']) && mb_strlen($data) < $schema['minLength']) {
            $errors[] = $path.': string shorter than '.$schema['minLength'];
        }

        if (is_int($data) || is_float($data)) {
            if (isset($schema['minimum']) && $data < $schema['minimum']) {
                $errors[] = $path.': below minimum '.$schema['minimum'];
            }
            if (isset($schema['maximum']) && $data > $schema['maximum']) {
                $errors[] = $path.': above maximum '.$schema['maximum'];
            }
        }

        if (is_array($data) && array_is_list($data) && in_array('array', $types, true)) {
            if (isset($schema['minItems']) && count($data) < $schema['minItems']) {
                $errors[] = $path.': fewer than '.$schema['minItems'].' items';
            }
            if (isset($schema['maxItems']) && count($data) > $schema['maxItems']) {
                $errors[] = $path.': more than '.$schema['maxItems'].' items';
            }
            if (isset($schema['items'])) {
                foreach ($data as $index => $item) {
                    array_push($errors, ...$this->validate($item, $schema['items'], $path.'['.$index.']'));
                }
            }
        }

        if (is_array($data) && in_array('object', $types, true)) {
            foreach ($schema['required'] ?? [] as $key) {
                if (! array_key_exists($key, $data)) {
                    $errors[] = $path.'.'.$key.': is required';
                }
            }

            $properties = $schema['properties'] ?? [];
            foreach ($data as $key => $value) {
                if (isset($properties[$key])) {
                    array_push($errors, ...$this->validate($value, $properties[$key], $path.'.'.$key));
                } elseif (($schema['additionalProperties'] ?? true) === false) {
                    $errors[] = $path.'.'.$key.': unexpected property';
                }
            }
        }

        return $errors;
    }

    /**
     * @param  list<string>  $types
     */
    private function matchesType(mixed $data, array $types): bool
    {
        foreach ($types as $type) {
            $ok = match ($type) {
                'null' => $data === null,
                'string' => is_string($data),
                'integer' => is_int($data),
                'number' => is_int($data) || is_float($data),
                'boolean' => is_bool($data),
                'array' => is_array($data) && array_is_list($data),
                'object' => is_array($data) && ($data === [] || ! array_is_list($data)),
                default => false,
            };

            if ($ok) {
                return true;
            }
        }

        return false;
    }
}
