<?php
namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class UserSetting extends Model
{
    protected $table = 'user_settings';
    protected $guarded = ['user_id'];
    protected $primaryKey = 'user_id';
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

    /**
     * Scope of FCM ID
     * @param  [type] $query     [description]
     * @param  [type] $fcm_id [description]
     */
    public function scopeFcmID($query, $fcm_id)
    {
        return $query->where('fcm_id', '=', $fcm_id);
    }
}
