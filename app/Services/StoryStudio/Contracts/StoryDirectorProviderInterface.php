<?php

namespace App\Services\StoryStudio\Contracts;

use App\Services\StoryStudio\Data\StructuredResult;

/**
 * A language model that returns JSON strictly matching a schema.
 */
interface StoryDirectorProviderInterface
{
    /**
     * @param  array<string, mixed>  $schema  JSON Schema (strict subset)
     */
    public function generateStructured(string $system, string $user, array $schema, string $schemaName): StructuredResult;

    public function name(): string;

    public function model(): string;
}
