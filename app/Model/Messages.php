<?php
namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Messages extends Model
{
    protected $table = 'messages';
    protected $guarded = ['message_id'];
    protected $primaryKey = 'message_id';

    public function business()
    {
        return $this->belongsTo('App\Model\Business', 'business_id');
    }

    public function userReceiveMessage()
    {
        return $this->hasMany('App\Model\UserReceiveMessage', 'message_id');
    }

    public function replyMessages()
    {
        return $this->hasMany('App\Model\ReplyMessages', 'message_id');
    }

    /**
     * get message in range
     * @param  [Integer] $range range.
     * @return [type]        [description]
     */
    public function scopeMessageInRange($query, $userId, $lat, $lon, $range, $ids)
    {
        // get all user's business deny
        $mBusinessDeny = app('db')
                        ->table(app('db')->raw('user_business_deny as ubd'))
                        ->select('ubd.business_id')
                        // ->from(app('db')->raw("user_business_deny as ubd"))
                        ->where('ubd.users_user_id', '=', $userId)->get();
        $idsDeny = [];
        foreach ($mBusinessDeny as $k => $v) {
            $idsDeny[] = $v->business_id;
        }

        $select = 'm.*, (6371 * acos (
                  cos ( radians(' . $lat . ') )
                  * cos( radians( b.lat ) )
                  * cos( radians( b.lon ) - radians(' . $lon . ') )
                  + sin ( radians(' . $lat . ') )
                  * sin( radians( b.lat ) )
                )
              ) AS distance';
        $query->select(app('db')->raw($select))
            ->from(app('db')->raw("messages as m"))
            ->leftJoin('businesses as b', function ($join) {
                $join->on('b.business_id', '=', 'm.business_id');
            })
            ->where('m.created_at', '>=', date('Y-m-d H:i:s', strtotime(date("Y-m-d H:i:s")." - 2 minutes")))
            ->whereNotIn('m.business_id', $idsDeny)
            ->whereNotIn('m.message_id', $ids)
            ->havingRaw('distance <= ' . $range);

        // $a = app('db')->select("select (NOW() + INTERVAL 7 HOUR - INTERVAL 1 MINUTE) as a");
        // var_dump(date('Y-m-d H:i:s', strtotime(date("Y-m-d H:i:s")." - 1 minutes")));
        // var_dump($query->toSql());
    }
}
