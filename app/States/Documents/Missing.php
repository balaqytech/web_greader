<?php

declare(strict_types=1);

namespace App\States\Documents;

class Missing extends DocumentState
{
    public static string $name = 'missing';

    public function getLabel(): string
    {
        return __('admin.document.states.missing');
    }

    public function getColor(): string
    {
        return 'gray';
    }
}
