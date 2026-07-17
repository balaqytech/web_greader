<?php

declare(strict_types=1);

namespace App\States\Documents;

class Uploaded extends DocumentState
{
    public static string $name = 'uploaded';

    public function getLabel(): string
    {
        return __('admin.document.states.uploaded');
    }

    public function getColor(): string
    {
        return 'info';
    }
}
