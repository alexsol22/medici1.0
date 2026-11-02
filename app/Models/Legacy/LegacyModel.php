<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

abstract class LegacyModel extends Model
{
    protected $connection = 'mysql';

    public $timestamps = false;

    protected $guarded = [];
}
