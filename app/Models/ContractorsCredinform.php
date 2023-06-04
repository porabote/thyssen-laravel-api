<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Porabote\Auth\Auth;

class ContractorsCredinform extends Model
{
    use HasFactory;

    protected $table = 'contractorsNone';
    public $timestamps = false;
    static public $limit = 1000;

    function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->connection = Auth::$user->account_alias . '_mysql';
    }

//    public function guid()
//    {
//        return $this->belongsTo(GuidsSchneider::class, 'guid_schneider_id', 'id' );
//    }

}
