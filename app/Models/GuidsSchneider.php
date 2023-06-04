<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GuidsSchneider extends Model
{
    protected $connection = 'api_mysql';
    protected $table = 'guids_schneider';

    protected $fillable = [
        'guid',
        'json_data',
        'component_id',
        'account_id',
    ];
}
