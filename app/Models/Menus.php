<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Kalnoy\Nestedset\NodeTrait;

class Menus extends Model
{
    use NodeTrait;

    protected $connection = 'auth_mysql';
    protected $table = 'menus';
    public $timestamps = false;

    protected $fillable = [
        'primary_key',
        'name',
        'link',
        "parent_id",
        "lft",
        "rght",
        "controller",
        "action",
        "plugin",
        "target",
        "flag",
        "aco_id",
    ];

    public function getLftName()
    {
        return 'lft';
    }

    public function getRgtName()
    {
        return 'rght';
    }

    public function getParentIdName()
    {
        return 'parent_id';
    }

    // Specify parent id attribute mutator
    public function setParentAttribute($value)
    {
        $this->setParentIdAttribute($value);
    }

}