<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Porabote\Auth\Auth;

class Passport extends Model
{
    use HasFactory;

    protected $connection = 'auth_mysql';
    protected $table = 'passports';

    protected $fillable = [
        'sery',
        'number',
        'date_of_issue',
        'date_of_expires',
        'user_id',
        'name',
        'name_en',
        'last_name',
        'last_name_en',
        'patronymic',
        'patronymic_en',
        'type',
    ];
}
