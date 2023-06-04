<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Observers\AccountObserver;

class AccessListsUsersContractors extends Model
{
    protected $connection = 'api_mysql';
    protected $table = 'access_lists_users_contractors';
    public $timestamps = false;

    protected $fillable = [
        'contractor_id',
        'access_lists_user_id',
    ];

    public static function boot() {
        parent::boot();
       // AccessListsUsers::observe(AccountObserver::class);
    }

    public function contractor()
    {
        return $this->belongsTo(Contractors::class, 'contractor_id', 'id' );
    }

}
