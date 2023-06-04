<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Observers\AccountObserver;

class AccessListsUsers extends Model
{
    protected $connection = 'api_mysql';
    protected $table = 'access_lists_users';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'access_list_id',
    ];

    public static function boot() {
        parent::boot();
        AccessListsUsers::observe(AccountObserver::class);
    }

    public function api_user()
    {
        return $this->belongsTo(ApiUsers::class, 'user_id', 'id');
    }

    public function contractors()
    {
        return $this->hasMany(AccessListsUsersContractors::class, 'access_lists_user_id', 'id');
    }

}
