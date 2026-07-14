<?php

use Tests\Support\CleanupAggregator;

it('attempts every step even when an earlier one throws', function () {
    $aggregator = new CleanupAggregator;
    $attempted = [];

    $aggregator->run('first', function () use (&$attempted) {
        $attempted[] = 'first';

        throw new RuntimeException('first failed');
    });
    $aggregator->run('second', function () use (&$attempted) {
        $attempted[] = 'second';
    });
    $aggregator->run('third', function () use (&$attempted) {
        $attempted[] = 'third';

        throw new RuntimeException('third failed');
    });

    expect($attempted)->toBe(['first', 'second', 'third'])
        ->and($aggregator->hasErrors())->toBeTrue()
        ->and($aggregator->errors())->toHaveCount(2);
});

it('reports no errors when every step succeeds', function () {
    $aggregator = new CleanupAggregator;

    $aggregator->run('first', fn () => null);
    $aggregator->run('second', fn () => null);

    expect($aggregator->hasErrors())->toBeFalse()
        ->and($aggregator->errors())->toBe([]);

    // A no-op: must not throw when nothing failed.
    $aggregator->throwIfAny();

    expect(true)->toBeTrue();
});

it('throws a single summarizing exception, chaining the first failure, once asked', function () {
    $aggregator = new CleanupAggregator;

    $aggregator->run('rollback', fn () => throw new RuntimeException('rollback exploded'));
    $aggregator->run('drop table', fn () => throw new RuntimeException('drop failed'));

    try {
        $aggregator->throwIfAny();
        expect(false)->toBeTrue('Expected throwIfAny() to throw.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toContain('2 cleanup step(s) failed')
            ->toContain('rollback')
            ->toContain('drop table')
            ->and($exception->getPrevious())->not->toBeNull()
            ->and($exception->getPrevious()->getMessage())->toContain('rollback exploded');
    }
});
