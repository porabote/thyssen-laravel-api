<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Porabote\Auth\Auth;

class PurchaseNomenclatures extends Model
{
    use HasFactory;

    public $timestamps = false;

    function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->connection = Auth::$user->account_alias . '_mysql';
    }

    public function request()
    {
        return $this->belongsTo(PurchaseRequest::class, 'request_id', 'id' );
    }

    public function manager()
    {
        return $this->belongsTo(ApiUsers::class, 'manager_id', 'id' );
    }

    public function status()
    {
        return $this->belongsTo(Statuses::class, 'status_id', 'id' );
    }

    public function purchase_request()
    {
        return $this->belongsTo(PurchaseRequest::class, 'request_id', 'id' );
    }

}
