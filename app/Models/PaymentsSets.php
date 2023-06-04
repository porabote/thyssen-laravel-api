<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Porabote\Auth\Auth;
use App\Observers\AuthObserver;

class PaymentsSets extends Model
{
    use HasFactory;

    protected $table = 'payments_sets';
    public static $limit = 50;

    public static function boot() {
        parent::boot();
        PaymentsSets::observe(AuthObserver::class);
    }

    function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->connection = Auth::$user->account_alias . '_mysql';
    }

    protected $fillable = [
        'date_payment',
        'week',
        'rate_euro',
        'rate_usd',
        'payments_count',
        'summa_eur',
        'summa_rur',
    ];

    public function files()
    {
        return $this->hasMany(File::class, 'record_id', 'id' )->where('model_alias','=', 'payments-sets');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class, 'record_id', 'id' )->where('class_name', '=', 'payments-sets')->orderBy('parent_id');
    }

    public function history()
    {
        return $this->hasMany(History::class, 'record_id', 'id' )->where('model_alias', '=', 'payments-sets');
    }

    public function payments()
    {
        return $this->hasMany(Payments::class, 'payments_set_id', 'id' );
    }

    public function user()
    {
        return $this->belongsTo(ApiUsers::class, 'user_id', 'id' );
    }

    // old todo delete
    public function sender()
    {
        return $this->belongsTo(ApiUsers::class, 'sender_id', 'id' );
    }

    public function excel_table()
    {
        return $this->hasOne(Files::class, 'record_id', 'id' )
            ->where('model_alias', 'Thyssen.PaymentsSets')
            ->where('flag', 'on');
    }
}
