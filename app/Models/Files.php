<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Porabote\Auth\Auth;
use App\Observers\AuthObserver;
use App\Observers\FilesObserver;

class Files extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'file_path',
        'basename',
        'ext',
        'size',
        'uri',
        'path',
        'user_id',
        'mime',
        'token',
        'width',
        'height',
        'flag',
        'title',
        'dscr',
        'label',
        'main',
        'model_alias',
        'parent_id',
        'record_id',
        'data_s_path',
        'data_json',
        'exported_at',
    ];

    function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->connection = Auth::$user->account_alias . '_mysql';
    }

    public static function boot() {
        parent::boot();
        Files::observe(AuthObserver::class);
        Files::observe(FilesObserver::class);
    }

    public function user()
    {
        return $this->belongsTo(Users::class);
    }

}
