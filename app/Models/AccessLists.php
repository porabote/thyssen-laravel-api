<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccessLists extends Model
{
    protected $connection = 'api_mysql';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'name',
        'component_id',
    ];
    
    public function stepsDefault()
    {
        return $this->hasMany(AcceptListsStepsDefault::class, 'accept_list_id', 'id' );
    }

    public function component()
    {
        return $this->belongsTo(Components::class, 'component_id', 'id' );
    }

}