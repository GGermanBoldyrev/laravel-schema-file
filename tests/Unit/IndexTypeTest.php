<?php

declare(strict_types=1);

use GGermanBoldyrev\SchemaFile\Schema\IndexType;

it('knows the Blueprint method and the name suffix of every index type', function (IndexType $type, string $method, string $suffix) {
    expect($type->method())->toBe($method)->and($type->suffix())->toBe($suffix);
})->with([
    [IndexType::Primary, 'primary', 'primary'],
    [IndexType::Unique, 'unique', 'unique'],
    [IndexType::Index, 'index', 'index'],
    [IndexType::FullText, 'fullText', 'fulltext'],
    [IndexType::Spatial, 'spatialIndex', 'spatialindex'],
]);

it('covers every case', function () {
    expect(IndexType::cases())->toHaveCount(5);
});
