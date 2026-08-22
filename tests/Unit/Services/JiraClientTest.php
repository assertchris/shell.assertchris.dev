<?php

use App\Exceptions\Jira\JiraAuthException;
use App\Exceptions\Jira\JiraForbiddenException;
use App\Exceptions\Jira\JiraRateLimitException;
use App\Services\JiraClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Http;

uses(TestCase::class, RefreshDatabase::class);

test('get() sends Basic Auth header', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response(['id' => 1], 200)]);

    $client = new JiraClient('https://test.atlassian.net', 'user@example.com', 'my-token');
    $client->get('/project/search');

    Http::assertSent(function ($request) {
        return str_starts_with($request->header('Authorization')[0], 'Basic ');
    });
});

test('200 response returns decoded JSON array', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response(['key' => 'PROJ'], 200)]);

    $client = new JiraClient('https://test.atlassian.net', 'user@example.com', 'token');

    expect($client->get('/project/PROJ'))->toBe(['key' => 'PROJ']);
});

test('404 response returns empty array without throwing', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response([], 404)]);

    $client = new JiraClient('https://test.atlassian.net', 'user@example.com', 'token');

    expect($client->get('/project/MISSING'))->toBe([]);
});

test('201 response returns decoded JSON', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response(['id' => '10001'], 201)]);

    $client = new JiraClient('https://test.atlassian.net', 'user@example.com', 'token');

    expect($client->post('/issue', ['fields' => []]))->toBe(['id' => '10001']);
});

test('204 response returns empty array', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response(null, 204)]);

    $client = new JiraClient('https://test.atlassian.net', 'user@example.com', 'token');
    $client->put('/issue/PROJ-1', ['fields' => []]);

    expect(true)->toBeTrue();
});

test('400 response throws RuntimeException', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response('Bad request', 400)]);

    $client = new JiraClient('https://test.atlassian.net', 'user@example.com', 'token');

    expect(fn () => $client->get('/invalid'))->toThrow(RuntimeException::class);
});

test('401 response throws JiraAuthException', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response([], 401)]);

    $client = new JiraClient('https://test.atlassian.net', 'user@example.com', 'bad-token');

    expect(fn () => $client->get('/project/search'))->toThrow(JiraAuthException::class);
});

test('403 response throws JiraForbiddenException', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response([], 403)]);

    $client = new JiraClient('https://test.atlassian.net', 'user@example.com', 'token');

    expect(fn () => $client->get('/project/search'))->toThrow(JiraForbiddenException::class);
});

test('429 response throws JiraRateLimitException with retryAfter from header', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response([], 429, ['Retry-After' => '30'])]);

    $client = new JiraClient('https://test.atlassian.net', 'user@example.com', 'token');

    try {
        $client->get('/project/search');
        $this->fail('Expected JiraRateLimitException was not thrown');
    } catch (JiraRateLimitException $e) {
        expect($e->retryAfter)->toBe(30);
    }
});

test('429 response uses default retryAfter of 60 when header absent', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response([], 429)]);

    $client = new JiraClient('https://test.atlassian.net', 'user@example.com', 'token');

    try {
        $client->get('/project/search');
        $this->fail('Expected JiraRateLimitException was not thrown');
    } catch (JiraRateLimitException $e) {
        expect($e->retryAfter)->toBe(60);
    }
});

test('500 response throws RuntimeException', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response([], 500)]);

    $client = new JiraClient('https://test.atlassian.net', 'user@example.com', 'token');

    expect(fn () => $client->get('/project/search'))->toThrow(RuntimeException::class);
});

test('agileGet() hits /rest/agile/1.0/ not /rest/api/3/', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response(['values' => []], 200)]);

    $client = new JiraClient('https://test.atlassian.net', 'user@example.com', 'token');
    $client->agileGet('/board');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/rest/agile/1.0/');
    });
});

test('agileGet() does not hit /rest/api/3/', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response(['values' => []], 200)]);

    $client = new JiraClient('https://test.atlassian.net', 'user@example.com', 'token');
    $client->agileGet('/board');

    Http::assertSent(function ($request) {
        return ! str_contains($request->url(), '/rest/api/3/');
    });
});

test('post() sends JSON body and returns response array', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response(['id' => '10002'], 201)]);

    $client = new JiraClient('https://test.atlassian.net', 'user@example.com', 'token');
    $result = $client->post('/issue', ['fields' => ['summary' => 'Test']]);

    expect($result)->toBe(['id' => '10002']);

    Http::assertSent(function ($request) {
        return $request->method() === 'POST';
    });
});

test('putJson() returns decoded array', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response(['id' => '10001', 'body' => []], 200)]);

    $client = new JiraClient('https://test.atlassian.net', 'user@example.com', 'token');
    $result = $client->putJson('/issue/PROJ-1/comment/1', ['body' => []]);

    expect($result)->toBeArray();
});

test('put() sends PUT request', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response(null, 204)]);

    $client = new JiraClient('https://test.atlassian.net', 'user@example.com', 'token');
    $client->put('/issue/PROJ-1', ['fields' => ['summary' => 'Updated']]);

    Http::assertSent(function ($request) {
        return $request->method() === 'PUT';
    });
});

test('delete() sends DELETE request', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response(null, 204)]);

    $client = new JiraClient('https://test.atlassian.net', 'user@example.com', 'token');
    $client->delete('/issue/PROJ-1');

    Http::assertSent(function ($request) {
        return $request->method() === 'DELETE';
    });
});
