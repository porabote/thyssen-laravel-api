<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Porabote\Auth\Auth;
use App\Observers\BillsObserver;

class Bills extends Model
{
    use HasFactory;

    public $timestamps = false;
    public $table = 'contract_extantions';

    function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->connection = Auth::$user->account_alias . '_mysql';
    }

    public static function boot() {
        parent::boot();
        Bills::observe(BillsObserver::class);
    }

    public function object()
    {
        return $this->belongsTo(Departments::class, 'object_id', 'id' );
    }

    public function comments()
    {
        return $this->hasMany(Comment::class, 'record_id', 'id' )
            ->whereIn('class_name', ['Store.Bills', 'bills'])
            ->orderBy('parent_id')
            ->orderByDesc('id');
    }

    public function user()
    {
        return $this->belongsTo(ApiUsers::class, 'manager_id', 'id' );
    }

    public function status()
    {
        return $this->belongsTo(Statuses::class, 'status_id', 'id' );
    }

    public function contractor()
    {
        return $this->belongsTo(Contractors::class, 'contractor_id', 'id' );
    }

    public function payment()
    {
        return $this->hasOne(Payments::class, 'business_request_id', 'id' );
    }

    public function payments()
    {
        return $this->hasMany(Payments::class, 'bill_id', 'id' );
    }

    public function client()
    {
        return $this->belongsTo(Contractors::class, 'client_id', 'id' );
    }

    public function steps()
    {
        return $this->hasMany(AcceptListsSteps::class, 'foreign_key')
            ->where('model', 'Bills')
            ->where('account_id', Auth::$user->account_id)
            ->where('active', '=', 1)
            ->orderBy('lft');
    }

    public function file_of_bill()
    {
        return $this->hasOne(Files::class, 'record_id', 'id' )
            ->where('model_alias', 'Store.Bills')
            ->where('flag', 'on')
            ->where('label', 'bill')
            ->orderByDesc('id');
    }

    public function files()
    {
        return $this->hasMany(Files::class, 'record_id', 'id' )
            ->where('model_alias', 'Store.Bills')
            ->where('flag', 'on')
            ->orderByDesc('id');
    }

    public function purchase_nomenclatures()
    {
        return $this->hasMany(PurchaseNomenclatures::class, 'bill_id');
    }

}
