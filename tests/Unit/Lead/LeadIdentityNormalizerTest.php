<?php

use App\Support\LeadIdentityNormalizer;

it('normalizes arabic alef variants to the same tokens', function () {
    $normalizer = new LeadIdentityNormalizer;

    $variants = ['أحمد', 'احمد', 'آحمد'];

    $normalized = array_map(
        fn (string $name) => $normalizer->normalizeName($name),
        $variants,
    );

    expect(array_unique($normalized))->toHaveCount(1);
});

it('builds the same fingerprint for arabic spelling variants', function () {
    $normalizer = new LeadIdentityNormalizer;

    $fingerprints = array_map(
        fn (string $name) => $normalizer->fingerprint('+96891111111', 1, 1, 1, $name),
        ['أحمد محمد', 'احمد محمد', 'آحمد محمد'],
    );

    expect(array_unique($fingerprints))->toHaveCount(1);
});

it('builds different fingerprints for different student names on the same phone', function () {
    $normalizer = new LeadIdentityNormalizer;

    $ahmed = $normalizer->fingerprint('+96891111111', 1, 1, 1, 'أحمد');
    $mohammed = $normalizer->fingerprint('+96891111111', 1, 1, 1, 'محمد');

    expect($ahmed)->not->toBe($mohammed);
});

it('detects token prefix relationships', function () {
    $normalizer = new LeadIdentityNormalizer;

    $shorter = $normalizer->tokenize('احمد محمد عبدالله');
    $longer = $normalizer->tokenize('احمد محمد عبدالله الهادي');

    expect($normalizer->isTokenPrefix($shorter, $longer))->toBeTrue();
});

it('detects single-token prefixes at the token level', function () {
    $normalizer = new LeadIdentityNormalizer;

    $shorter = $normalizer->tokenize('أحمد');
    $longer = $normalizer->tokenize('أحمد محمد عبدالله');

    expect($normalizer->isTokenPrefix($shorter, $longer))->toBeTrue();
});

it('prefers the longer display name', function () {
    $normalizer = new LeadIdentityNormalizer;

    expect($normalizer->preferLongerDisplayName(
        'احمد محمد عبدالله',
        'احمد محمد عبدالله الهادي',
    ))->toBe('احمد محمد عبدالله الهادي');
});
