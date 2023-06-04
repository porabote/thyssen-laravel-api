<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use App\Observers\AuthObserver;
use Porabote\Auth\Auth;

class Companies extends Model
{

    //const CREATED_AT = 'created';
   // const UPDATED_AT = 'post_modified';
    static public $limit = 1000;

    public static function boot() {
        parent::boot();
        Companies::observe(AuthObserver::class);
    }

    function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->connection = Auth::$user->account_alias . '_mysql';
    }

    protected $fillable = [
        'name',
        'full_name',
        'active',
        'inn',
        'okpo',
        'kpp',
        'address_ur',
        'address_fact',
        'address_post',
        'rs',
        'osnovanie',
        'email',
        'user_id',
        'self',
        'tax_id',
        'post',
        'type_id',
        'statuse_id',
        'task_count',
        'created',
        'count_modified',
        'city_id',
        'gd_id',
        'gb_id',
        'datas',
        'cg_id',
        'ip',
        'ogrn',
        'activity',
        'cl_id',
        'oktmo',
        'brandName',
        'credinform_guid',
    ];

    public function logo()
    {
        return $this->hasOne(File::class, 'record_id', 'id' )
            ->where('model_alias', 'Companies')
            ->orderByDesc('id')
            ->where('label', 'logo');
    }

}
