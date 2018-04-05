<?php
namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class ReplyMessages extends Model
{
    protected $table = 'reply_messages';
    protected $guarded = ['reply_id'];
    protected $primaryKey = 'reply_id';

    public $timestamps = false;

    public function messages()
    {
        return $this->hasOne('App\Model\Messages', 'message_id');
    }

    public function sender()
    {
        return $this->hasOne('App\Model\User', 'user_id');
    }

    /**
     * Scope of User Id
     * @param  [type] $query     [description]
     * @param  [type] $user_id [description]
     */
    public function scopeSenderID($query, $user_id)
    {
        return $query->where('sender_id', '=', $user_id);
    }

    /**
     * Scope of Message Id
     * @param  [type] $query     [description]
     * @param  [type] $message_id [description]
     */
    public function scopeMessageID($query, $message_id)
    {
        return $query->where('message_id', '=', $message_id);
    }
}
