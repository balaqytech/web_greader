<?php

use Illuminate\Routing\Route;
use Illuminate\Support\Collection;

test('the Postman collection covers every registered API v1 route exactly once', function () {
    $collectionPath = base_path('docs/greader-api.postman_collection.json');

    expect($collectionPath)->toBeFile();

    /** @var array{item: list<array<string, mixed>>, variable: list<array<string, mixed>>} $collection */
    $collection = json_decode(
        file_get_contents($collectionPath),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    $registeredRoutes = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn (Route $route): bool => str_starts_with($route->uri(), 'api/v1/'))
        ->flatMap(function (Route $route): array {
            return collect($route->methods())
                ->reject(fn (string $method): bool => in_array($method, ['HEAD', 'OPTIONS'], true))
                ->map(fn (string $method): string => "{$method} ".normalizeRoutePath("/{$route->uri()}"))
                ->all();
        })
        ->sort()
        ->values()
        ->all();

    $collectionRequests = postmanCollectionRequests($collection['item']);

    $collectionRoutes = $collectionRequests
        ->map(fn (array $request): string => "{$request['method']} ".normalizeRoutePath($request['path']))
        ->sort()
        ->values()
        ->all();

    $variables = collect($collection['variable'])->pluck('value', 'key');

    expect($variables->get('base_url'))->toBe('http://greader.test')
        ->and($collectionRoutes)->toBe($registeredRoutes)
        ->and($collectionRequests)->toHaveCount(count($registeredRoutes))
        ->and($collectionRequests->every(
            fn (array $request): bool => $request['url_is_string']
                && str_starts_with($request['raw_url'], '{{base_url}}/api/v1/'),
        ))->toBeTrue();
});

test('every Postman request explicitly accepts JSON', function () {
    /** @var array{item: list<array<string, mixed>>} $collection */
    $collection = json_decode(
        file_get_contents(base_path('docs/greader-api.postman_collection.json')),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    $requests = postmanCollectionRequests($collection['item']);

    expect($requests)->not->toBeEmpty()
        ->and($requests->every(function (array $request): bool {
            return collect($request['headers'])->contains(
                fn (array $header): bool => strtolower((string) ($header['key'] ?? '')) === 'accept'
                    && strtolower((string) ($header['value'] ?? '')) === 'application/json'
                    && ($header['disabled'] ?? false) !== true,
            );
        }))->toBeTrue();
});

test('Postman phone samples use the canonical international format', function () {
    /** @var array{item: list<array<string, mixed>>, variable: list<array<string, mixed>>} $collection */
    $collection = json_decode(
        file_get_contents(base_path('docs/greader-api.postman_collection.json')),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    $variables = collect($collection['variable'])->pluck('value', 'key');
    $guardianPhone = $variables->get('guardianPhone');

    $phoneSamples = postmanCollectionRequests($collection['item'])
        ->pluck('raw_body')
        ->filter()
        ->flatMap(function (string $rawBody) use ($guardianPhone): array {
            preg_match_all(
                '/"(?:whatsapp|mother_phone|guardian_phone)"\s*:\s*"([^"]+)"/',
                $rawBody,
                $matches,
            );

            return array_map(
                fn (string $phone): mixed => $phone === '{{guardianPhone}}' ? $guardianPhone : $phone,
                $matches[1],
            );
        });

    expect($guardianPhone)->toBeString()->toStartWith('+')
        ->and($phoneSamples)->not->toBeEmpty()
        ->and($phoneSamples->every(
            fn (mixed $phone): bool => is_string($phone) && normalize_phone_number($phone) === $phone,
        ))->toBeTrue();
});

/**
 * @param  list<array<string, mixed>>  $items
 * @return Collection<int, array{
 *     method: string,
 *     path: string,
 *     raw_url: string,
 *     url_is_string: bool,
 *     headers: list<array<string, mixed>>,
 *     raw_body: ?string
 * }>
 */
function postmanCollectionRequests(array $items): Collection
{
    return collect($items)->flatMap(function (array $item): Collection {
        if (isset($item['item']) && is_array($item['item'])) {
            return postmanCollectionRequests($item['item']);
        }

        $request = $item['request'] ?? null;

        if (! is_array($request) || ! isset($request['method'], $request['url'])) {
            return collect();
        }

        $url = $request['url'];
        $rawUrl = is_array($url) ? ($url['raw'] ?? null) : $url;

        if (! is_string($rawUrl) || $rawUrl === '') {
            return collect();
        }

        $path = parse_url($rawUrl, PHP_URL_PATH);
        $path = is_string($path) ? $path : explode('?', $rawUrl, 2)[0];
        $path = preg_replace('/^\\{\\{base_url\\}\\}/', '', $path) ?? $path;
        $path = preg_replace('/\\{\\{[^}]+\\}\\}/', '{parameter}', $path) ?? $path;

        return collect([[
            'method' => (string) $request['method'],
            'path' => $path,
            'raw_url' => $rawUrl,
            'url_is_string' => is_string($url),
            'headers' => is_array($request['header'] ?? null) ? $request['header'] : [],
            'raw_body' => is_string($request['body']['raw'] ?? null) ? $request['body']['raw'] : null,
        ]]);
    });
}

function normalizeRoutePath(string $path): string
{
    return preg_replace('/\\{[^}]+\\}/', '{}', $path) ?? $path;
}
