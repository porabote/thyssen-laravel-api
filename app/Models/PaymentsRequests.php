<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Porabote\Auth\Auth;

class PaymentsRequests extends Model
{
    use HasFactory;

    protected $table = 'payments_requests';

    public $timestamps = false;

    protected $fillable = [
        'record_id',
        'date_created',
        'data_json',
        'flag',
        'status_id',
        'post_id',
        'contractor_id',
        'acceptor_id',
        'delta',
        'summa',
        'comment',
        'bill_id',
    ];

    function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->connection = Auth::$user->account_alias . '_mysql';
    }

    public function comments()
    {
        return $this->hasMany(Comment::class, 'record_id', 'id' )
            ->whereIn('class_name', ['PaymentsRequests', 'PaymenentsRequests'])
            ->orderBy('parent_id')
            ->orderByDesc('id');
    }

    public function user()
    {
        return $this->belongsTo(ApiUsers::class, 'post_id', 'id' );
    }

    public function bill()
    {
        return $this->belongsTo(Bills::class, 'bill_id', 'id' );
    }

    public function contractor()
    {
        return $this->belongsTo(Contractors::class, 'contractor_id', 'id' );
    }

    public function status()
    {
        return $this->belongsTo(Statuses::class, 'status_id', 'id' );
    }

    public function post()
    {
        return $this->belongsTo(ApiUsers::class, 'post_id', 'id' );
    }
//
//$this->hasMany('Files', [
//'foreignKey' => 'record_id',
//'className' => 'Files',
//'conditions' => [ 'model_alias' => 'App.BusinessRequests', 'flag' => 'on' ]
//]);
//
//$this->hasOne('Payments', [
//    //'foreignKey' => 'record_id',
//    //'className' => 'Files'
//]);






}
