<?php

use App\Exceptions\GitHub\GitHubAuthException;
use App\Exceptions\GitHub\GitHubRateLimitException;
use App\Exceptions\GitHub\GitHubScopeException;
use App\Models\Account;
use App\Services\GitHubClient;
use App\Services\GitHubService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Http;

uses(TestCase::class, RefreshDatabase::class);

test('request uses Authorization token header not Bearer', function () {
    Http::fake(['https://api.github.com/*' => Http::response(['id' => 1], 200)]);

    $client = new GitHubClient('my-secret-pat');
    $client->get('/user/repos');

    Http::assertSent(function ($request) {
        return $request->header('Authorization')[0] === 'token my-secret-pat';
    });
});

test('401 response throws GitHubAuthException', function () {
    Http::fake(['https://api.github.com/*' => Http::response([], 401)]);

    $client = new GitHubClient('bad-pat');

    expect(fn () => $client->get('/user/repos'))->toThrow(GitHubAuthException::class);
});

test('403 response throws GitHubScopeException', function () {
    Http::fake(['https://api.github.com/*' => Http::response([], 403)]);

    $client = new GitHubClient('limited-pat');

    expect(fn () => $client->get('/user/repos'))->toThrow(GitHubScopeException::class);
});

test('429 response throws GitHubRateLimitException with retryAfter from header', function () {
    Http::fake(['https://api.github.com/*' => Http::response([], 429, ['Retry-After' => '42'])]);

    $client = new GitHubClient('some-pat');

    try {
        $client->get('/user/repos');
        $this->fail('Expected GitHubRateLimitException was not thrown');
    } catch (GitHubRateLimitException $e) {
        expect($e->retryAfter)->toBe(42);
    }
});

test('429 response uses default retryAfter of 60 when header is absent', function () {
    Http::fake(['https://api.github.com/*' => Http::response([], 429)]);

    $client = new GitHubClient('some-pat');

    try {
        $client->get('/user/repos');
        $this->fail('Expected GitHubRateLimitException was not thrown');
    } catch (GitHubRateLimitException $e) {
        expect($e->retryAfter)->toBe(60);
    }
});

test('404 response returns empty array', function () {
    Http::fake(['https://api.github.com/*' => Http::response([], 404)]);

    $client = new GitHubClient('some-pat');

    expect($client->get('/repos/owner/missing'))->toBe([]);
});

test('non-2xx response other than handled codes throws RuntimeException', function () {
    Http::fake(['https://api.github.com/*' => Http::response([], 500)]);

    $client = new GitHubClient('some-pat');

    expect(fn () => $client->get('/user/repos'))->toThrow(RuntimeException::class);
});

test('searchPulls appends is:pr to the query string', function () {
    Http::fake(['https://api.github.com/*' => Http::response(['items' => []], 200)]);

    $account = Account::factory()->withGitHub()->create();
    $service = new GitHubService($account);
    $service->searchPulls('repo:owner/repo');

    Http::assertSent(function ($request) {
        parse_str(parse_url($request->url(), PHP_URL_QUERY), $params);

        return str_contains($params['q'] ?? '', 'is:pr');
    });
});

test('searchIssues appends is:issue to the query string', function () {
    Http::fake(['https://api.github.com/*' => Http::response(['items' => []], 200)]);

    $account = Account::factory()->withGitHub()->create();
    $service = new GitHubService($account);
    $service->searchIssues('repo:owner/repo');

    Http::assertSent(function ($request) {
        parse_str(parse_url($request->url(), PHP_URL_QUERY), $params);

        return str_contains($params['q'] ?? '', 'is:issue');
    });
});
