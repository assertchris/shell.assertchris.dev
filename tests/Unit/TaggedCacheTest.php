<?php

use AC\LaravelTaggedCache\TaggedCache;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Config;

uses(TestCase::class);

beforeEach(function () {
    Config::set('cache.default', 'array');
});

test('remember stores and retrieves a value', function () {
    $result = TaggedCache::for(tags: ['test'], context: ['key' => 'remember'])
        ->remember(60, fn () => 'hello');

    expect($result)->toBe('hello');

    $second = TaggedCache::for(tags: ['test'], context: ['key' => 'remember'])
        ->remember(60, fn () => 'should-not-be-called');

    expect($second)->toBe('hello');
});

test('get retrieves a stored value', function () {
    TaggedCache::for(tags: ['test'], context: ['key' => 'get-test'])
        ->put('stored-value', 60);

    $result = TaggedCache::for(tags: ['test'], context: ['key' => 'get-test'])
        ->get();

    expect($result)->toBe('stored-value');
});

test('flush clears all entries under the tag', function () {
    TaggedCache::for(tags: ['flushable'], context: ['key' => 'entry-one'])
        ->put('value-one', 60);

    TaggedCache::for(tags: ['flushable'], context: ['key' => 'entry-two'])
        ->put('value-two', 60);

    TaggedCache::for(tags: ['flushable'])->flush();

    expect(TaggedCache::for(tags: ['flushable'], context: ['key' => 'entry-one'])->get())->toBeNull();
    expect(TaggedCache::for(tags: ['flushable'], context: ['key' => 'entry-two'])->get())->toBeNull();
});
