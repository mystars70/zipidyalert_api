<?php
namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class UserBusinesses extends Model
{
    protected $table = 'users_businesses';
    protected $fillable = ['user_id', 'business_id', 'user_type', 'status'];

    public $timestamps = false;

    public function user()
    {
        return $this->belongsTo('App\Model\User', 'user_id');
    }

    public function business()
    {
        return $this->belongsTo('App\Model\Business', 'business_id');
    }

    /**
     * Scope of user id
     * @param  [type] $query     [description]
     * @param  [type] $token_key [description]
     */
    public function scopeUserID($query, $user_id)
    {
        return $query->where('user_id', '=', $user_id);
    }

    /**
     * Scope of business id
     * @param  [type] $query     [description]
     * @param  [type] $business_id [description]
     */
    public function scopeBusinessID($query, $business_id)
    {
        return $query->where('business_id', '=', $business_id);
    }

    /**
     * Scope of user type
     * @param  [type] $query     [description]
     * @param  [type] $user_type [description]
     */
    public function scopeUserType($query, $user_type)
    {
        return $query->whereIn('user_type', $user_type);
    }

    /**
     * Scope of $status
     * @param  [type] $query     [description]
     * @param  [type] $status [description]
     */
    public function scopeStatus($query, $status)
    {
        return $query->where('status', '=', $status);
    }
}
