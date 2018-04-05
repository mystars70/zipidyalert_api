<?php
namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $table = 'users';
    protected $guarded = ['user_id'];
    protected $primaryKey = 'user_id';

    public function invoices()
    {
        return $this->hasMany('App\Model\Invoices');
    }

    public function userSetting()
    {
        return $this->hasOne('App\Model\UserSetting', 'user_id');
    }

    public function userBusinesses()
    {
        return $this->hasMany('App\Model\UserBusinesses', 'user_id');
    }

    public function userReceiveMessage()
    {
        return $this->hasMany('App\Model\UserReceiveMessage', 'user_id');
    }

    /**
     * Scope of Token key
     * @param  [type] $query     [description]
     * @param  [type] $token_key [description]
     */
    public function scopeTokenKey($query, $token_key)
    {
        return $query->where('token_key', '=', $token_key);
    }

    /**
     * Scope of Username
     * @param  [type] $query    [description]
     * @param  [type] $username [description]
     * @return [type]           [description]
     */
    public function scopeUsername($query, $username)
    {
        return $query->where('username', '=', $username);
    }

    /**
     * Scope of email
     * @param  [type] $query    [description]
     * @param  [type] $email [description]
     * @return [type]           [description]
     */
    public function scopeEmail($query, $email)
    {
        return $query->where('email', '=', $email);
    }

    /**
     * Scope Member is actived
     * @param  [type] $query [description]
     * @return [type]        [description]
     */
    public function scopeActived($query)
    {
        return $query->where('status', '=', 1);
    }

    /**
     * get user in range businesses
     * @param  [type] $query     [description]
     * @param  [Business Model] $mBusiness businesses model.
     * @return [type]            [description]
     */
    public function scopeUserInRange($query, $mBusiness)
    {
        // get all user's business deny
        $mUserDeny = app('db')->table(app('db')->raw('users as u1'))
                        ->select('u1.user_id')
                        ->join('user_business_deny as ubd', function ($join) {
                            $join->on('u1.user_id', '=', 'ubd.users_user_id');
                        })
                        ->where('ubd.business_id', '=', $mBusiness->business_id)
                        ->get();
        $idsDeny = [];
        foreach ($mUserDeny as $k => $v) {
            $idsDeny[] = $v->user_id;
        }

        $select = 'u.*, us.radius, (6371 * acos (
                  cos ( radians(' . $mBusiness->lat . ') )
                  * cos( radians( u.lat ) )
                  * cos( radians( u.lon ) - radians(' . $mBusiness->lon . ') )
                  + sin ( radians(' . $mBusiness->lat . ') )
                  * sin( radians( u.lat ) )
                )
              ) AS distance';
        $query->select(app('db')->raw($select))
            ->from(app('db')->raw("users as u"))
            ->leftJoin('user_settings as us', function ($join) {
                $join->on('us.user_id', '=', 'u.user_id');
            })
            // ->havingRaw('distance <= us.radius');
            ->havingRaw('distance <= ' . env('NOTIFICATION_RANGE', '1'));

        if (!empty($idsDeny)) {
            $query->whereNotIn('u.user_id', $idsDeny);
        }
        // var_dump($query->toSql());
        return $query;
    }


    /**
     * Scope of City ID
     * @param  [type] $city_id     [description]
     * @param  [type] $token_key [description]
     */
    public function scopeCityID($query, $city_id)
    {
        return $query->where('city_id', '=', $city_id);
    }
}
