<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiUsers extends Model
{
    protected $connection = 'auth_mysql';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'users';
    static public $limit = 1000;

    protected $fillable = [
        'email',
        'email_extra',
        'name',
        'patronymic',
        'post_name',
        'status',
        'department_id',
        'password',
        'role_id',
        'shift_id',
        'sex',
        'date_birth',
        'city_id',
    ];

    static public $allowed_attributes = [
        'email',
        'email_extra',
        'name',
        'patronymic',
        'post_name',
        'status',
        'department_id',
        'role_id',
        'shift_id',
        'sex',
        'date_birth',
        'phone',
        'sex',
        'city_id',
    ];

    protected $hidden = [
        'password',
    ];

    public function scopeStatus($query)
    {
        return $query->where('status', '=', 'Active');
    }

    public function user()
    {
        return $this->belongsToMany(Dialogs::class);
//            ->as('subscription')
//            ->withTimestamps();
    }

    public function avatar()
    {
        return $this->hasOne(File::class, 'record_id', 'id' )
            ->where('model_alias', 'Users')
            ->where('label', 'main');
    }

    public function department()
    {
        return $this->belongsTo(Departments::class, 'department_id', 'id');
    }

    public function aro()
    {
        return $this->hasOne(AclAros::class, 'foreign_key', 'id' )
            ->where('label', 'User');
    }

    public function aro_local()
    {
        return $this->hasOne(AclArosLocal::class, 'foreign_key', 'id' )
            ->where('label', 'User');
    }

    public function users_requests()
    {
        return $this->hasMany(UsersRequests::class, 'user_id', 'id' );
    }

    public function shift()
    {
        return $this->belongsTo(Shifts::class, 'shift_id', 'id');
    }

    public function shiftworkers()
    {
        return $this->hasMany(UsersShiftworkers::class, 'user_id', 'id');
    }

    public function passport()
    {
        return $this->hasOne(Passport::class, 'user_id', 'id' )
            ->where('type', 'russian');
    }

    public function passport_foreign()
    {
        return $this->hasOne(Passport::class, 'user_id', 'id' )
            ->where('type', 'foreign');
    }

    public function facsimiles()
    {
        return $this->hasMany(Files::class, 'record_id', 'id')
            ->where('model_alias', 'Users')
            ->where('label', 'facsimile')
            ->where('flag', 'on');
    }
}
