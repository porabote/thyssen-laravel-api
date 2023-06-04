<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Observers\AuthObserver;
use App\Observers\HistoryObserver;
use Porabote\Auth\Auth;

class TicketsRequests extends Model
{
    protected $connection = 'api_mysql';

    public static function boot() {
        parent::boot();
        TicketsRequests::observe(HistoryObserver::class);
        TicketsRequests::observe(AuthObserver::class);
    }

    protected $fillable = [
        'id',
        'type_id',
        'comment',
        'date',
        'city_from_id',
        'city_to_id',
    ];

    public function user()
    {
        return $this->belongsTo(ApiUsers::class, 'user_id', 'id' );
    }

    public function status()
    {
        return $this->belongsTo(Statuses::class, 'status_id', 'id' );
    }

    public function comments()
    {
        return $this->hasMany(Comment::class, 'record_id', 'id' )
            ->where('class_name', '=', 'TicketsRequests')
            ->orderByDesc('id');
    }

    public function files()
    {
        return $this->hasMany(File::class, 'record_id', 'id' )
            ->where('model_alias', '=', 'TicketsRequests')
            ->where('flag', '=', 'on')
            ->orderByDesc('id');
    }

    public function history()
    {
        return $this->hasMany(History::class, 'record_id', 'id' )
            ->where('model_alias', '=', 'TicketsRequests')
            ->orderByDesc('id');
    }

    public function steps()
    {
        return $this->hasMany(AcceptListsSteps::class, 'foreign_key')
            ->where('model', 'TicketsRequests')
            ->where('account_id', Auth::$user->account_id)
            ->where('active', '=', 1)
            ->orderBy('lft');
    }

    public function tickets()
    {
        return $this->hasMany(Tickets::class, 'ticket_request_id');
    }

    public function city_from()
    {
        return $this->belongsTo(Cities::class, 'city_from_id', 'id' );
    }

    public function city_to()
    {
        return $this->belongsTo(Cities::class, 'city_to_id', 'id' );
    }

}