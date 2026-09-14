<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/*
|--------------------------------------------------------------------------
| Turnstile Fake Helpers
|--------------------------------------------------------------------------
|
| Cloudflare Turnstile is always verified in production via an HTTP call to
| challenges.cloudflare.com. Tests must never perform that real network call,
| so these helpers fake the Http facade for the siteverify endpoint only.
|
*/

/**
 * Fake Cloudflare Turnstile to report the token as VALID.
 */
function fakeTurnstileSuccess(): void
{
    \Illuminate\Support\Facades\Http::fake([
        'challenges.cloudflare.com/turnstile/*' =>
            \Illuminate\Support\Facades\Http::response(['success' => true], 200),
    ]);
}

/**
 * Fake Cloudflare Turnstile to report the token as INVALID.
 */
function fakeTurnstileFailure(): void
{
    \Illuminate\Support\Facades\Http::fake([
        'challenges.cloudflare.com/turnstile/*' =>
            \Illuminate\Support\Facades\Http::response(['success' => false], 200),
    ]);
}
