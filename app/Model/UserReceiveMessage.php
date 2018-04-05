<?php
namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class UserReceiveMessage extends Model
{
    protected $table = 'user_receive_message';
    protected $fillable = ['user_id', 'message_id', 'created_at'];

    /**
    * Indicates if the model should be timestamped.
    *
    * @var bool
    */
    public $timestamps = false;

    public function user()
    {
        return $this->belongsTo('App\Model\User', 'user_id');
    }

    public function message()
    {
        return $this->belongsTo('App\Model\Messages', 'message_id');
    }

    public function business()
    {
        return $this->belongsTo('App\Model\Business', 'business_id');
    }

    /**
     * Scope of user id
     * @param  [type] $query     [description]
     * @param  [type] $user_id [description]
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
        return $query->where('user_id', '=', $business_id);
    }
}
