<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Porabote\Auth\Auth;
use App\Observers\AuthObserver;

class Contractors extends Model
{
    use HasFactory;

    protected $table = 'contractors';
    static public $limit = 8000;

    public static function boot() {
        parent::boot();
        Contractors::observe(AuthObserver::class);
    }

    protected $fillable = [
        'name',
        'inn',
        'kpp',
        'type',
        'record_id',
        'model',
        'okpo',
        'date_created',
        'guid_schneider_id',
        'guid_schneider',
        'companyId',
        'credinform_guid',
        'source',
        'date_from',
        'date_to',
        'source_type',
        'user_id',
        'created_at',
    ];

    function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->connection = Auth::$user->account_alias . '_mysql';
    }

    public function guid()
    {
        return $this->belongsTo(GuidsSchneider::class, 'guid_schneider_id', 'id' );
    }

}
