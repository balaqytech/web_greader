<?php

use Illuminate\Routing\Route;
use Illuminate\Support\Collection;

test('the Postman collection covers every registered API v1 route exactly once', function () {
    $collectionPath = base_path('docs/greader-api.postman_collection.json');

    expect($collectionPath)->toBeFile();

    /** @var array{item: list<array<string, mixed>>} $collection */
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

    $collectionRoutes = postmanCollectionRequests($collection['item'])
        ->map(fn (array $request): string => "{$request['method']} ".normalizeRoutePath($request['path']))
        ->sort()
        ->values()
        ->all();

    expect($collectionRoutes)->toBe($registeredRoutes);
});

/**
 * @param  list<array<string, mixed>>  $items
 * @return Collection<int, array{method: string, path: string}>
 */
function postmanCollectionRequests(array $items): Collection
{
    return collect($items)->flatMap(function (array $item): Collection {
        if (isset($item['item']) && is_array($item['item'])) {
            return postmanCollectionRequests($item['item']);
        }

        $request = $item['request'] ?? null;

        if (! is_array($request) || ! isset($request['method'], $request['url']['raw'])) {
            return collect();
        }

        $rawUrl = (string) $request['url']['raw'];
        $path = explode('?', $rawUrl, 2)[0];
        $path = preg_replace('/^\\{\\{baseUrl\\}\\}/', '', $path) ?? $path;
        $path = preg_replace('/\\{\\{[^}]+\\}\\}/', '{parameter}', $path) ?? $path;

        return collect([[
            'method' => (string) $request['method'],
            'path' => $path,
        ]]);
    });
}

function normalizeRoutePath(string $path): string
{
    return preg_replace('/\\{[^}]+\\}/', '{}', $path) ?? $path;
}
