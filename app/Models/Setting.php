<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Model;

/**
 * Raw key/value settings row. Callers should not read this model directly — go through a
 * typed accessor (e.g. `App\Support\Settings\PaymentSettings`), which owns caching,
 * interpretation, and the "configured vs. unset" distinction.
 *
 * Extends the auditable base model deliberately: changing the registration fee is a
 * financial action, so every write here carries an audit trail of who changed what.
 *
 * @property string $key
 * @property string|null $value
 */
class Setting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'key',
        'value',
    ];
}
