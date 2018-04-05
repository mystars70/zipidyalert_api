<?php
namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Business extends Model
{
    protected $table = 'businesses';
    protected $guarded = ['business_id'];
    protected $primaryKey = 'business_id';

    public function invoice()
    {
        return $this->hasOne('App\Model\Invoices');
    }

    public function messages()
    {
        return $this->hasMany('App\Model\Messages', 'business_id');
    }

    public function userBusiness()
    {
        return $this->hasMany('App\Model\UserBusinesses', 'business_id');
    }

    /**
     * Scope of search all by name
     * @param  [type] $query      [description]
     * @param  [type] $state_code [description]
     * @return [type]             [description]
     */
    public function scopeLikeName($query, $name)
    {
        return $query->where(app('db')->raw('LOWER(name)'), 'like', '%' . mb_strtolower($name) . '%');
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
