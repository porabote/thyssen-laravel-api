<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use App\Observers\AuthObserver;
use Porabote\Auth\Auth;

class Entrepreneurs extends Model
{
    static public $limit = 1000;

    public static function boot() {
        parent::boot();
        Entrepreneurs::observe(AuthObserver::class);
    }

    function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->connection = Auth::$user->account_alias . '_mysql';
    }

    protected $fillable = [
        'name',
        'full_name',
        'inn',
        'okpo',
        'oktmo',
        'ogrn',
        'ogrn',
        'data_s',
        'tax_id',
        'city_id',
        'brandName',
        'flag',
        'credinform_guid',
    ];

}
