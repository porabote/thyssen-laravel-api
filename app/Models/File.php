<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Observers\AuthObserver;
use App\Observers\FilesObserver;

class File extends Model
{
    protected $connection = 'api_mysql';

    use HasFactory;

    protected $fillable = [
        'name',
        'file_path',
        'basename',
        'ext',
        'uri',
        'path',
        'user_id',
        'mime',
        'token',
        'width',
        'height',
        'size',
        'flag',
        'title',
        'dscr',
        'label',
        'main',
        'model_alias',
        'record_id',
        'data_s_path',
        'account_id',
        'parent_id',
        'data_json',
    ];

    public static function boot() {
        parent::boot();
        File::observe(AuthObserver::class);
        File::observe(FilesObserver::class);
    }

}
