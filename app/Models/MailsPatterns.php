<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MailsPatterns extends Model
{
    use HasFactory;

    protected $connection = 'api_mysql';
   // public $table = 'mails';
}
