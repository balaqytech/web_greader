<?php

declare(strict_types=1);

namespace App\States\Documents;

class Approved extends DocumentState
{
    public static string $name = 'approved';

    public function getLabel(): string
    {
        return __('admin.document.states.approved');
    }

    public function getColor(): string
    {
        return 'success';
    }
}
