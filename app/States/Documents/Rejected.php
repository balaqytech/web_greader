<?php

declare(strict_types=1);

namespace App\States\Documents;

class Rejected extends DocumentState
{
    public static string $name = 'rejected';

    public function getLabel(): string
    {
        return __('admin.document.states.rejected');
    }

    public function getColor(): string
    {
        return 'danger';
    }
}
