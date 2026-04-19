<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use OwenIt\Auditing\Audit;
use OwenIt\Auditing\Contracts\Auditable;

class Model extends EloquentModel implements Auditable
{
    use \OwenIt\Auditing\Auditable;
}
