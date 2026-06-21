<?php

declare(strict_types=1);

use Xrea\Agent\Http\Auth;

it('allows access when token is empty', function (): void {
    $auth = new Auth('');
    expect($auth->isRequired())->toBeFalse()
        ->and($auth->authorize([]))->toBeTrue();
});

it('requires auth when token is non-empty', function (): void {
    $auth = new Auth('secret-token');
    expect($auth->isRequired())->toBeTrue();
});

it('parses bearer token from HTTP_AUTHORIZATION', function (): void {
    $token = Auth::bearerTokenFromServer([
        'HTTP_AUTHORIZATION' => 'Bearer my-secret-token',
    ]);
    expect($token)->toBe('my-secret-token');
});

it('parses bearer token case-insensitively', function (): void {
    expect(Auth::bearerTokenFromServer(['HTTP_AUTHORIZATION' => 'BEARER test']))->toBe('test')
        ->and(Auth::bearerTokenFromServer(['HTTP_AUTHORIZATION' => 'Bearer TEST']))->toBe('TEST');
});

it('accepts REDIRECT_HTTP_AUTHORIZATION fallback', function (): void {
    $token = Auth::bearerTokenFromServer([
        'REDIRECT_HTTP_AUTHORIZATION' => 'Bearer redirect-token',
    ]);
    expect($token)->toBe('redirect-token');
});

it('returns null for non-bearer headers', function (): void {
    expect(Auth::bearerTokenFromServer(['HTTP_AUTHORIZATION' => 'Basic dXNlcjpwYXM=']))->toBeNull()
        ->and(Auth::bearerTokenFromServer(['HTTP_AUTHORIZATION' => '']))->toBeNull();
});

it('rejects wrong token', function (): void {
    $auth = new Auth('correct-token');
    expect($auth->authorize([
        'HTTP_AUTHORIZATION' => 'Bearer wrong-token',
    ]))->toBeFalse();
});

it('accepts correct token', function (): void {
    $auth = new Auth('my-secret-token');
    expect($auth->authorize([
        'HTTP_AUTHORIZATION' => 'Bearer my-secret-token',
    ]))->toBeTrue();
});
