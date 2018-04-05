<?php
namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Cities extends Model
{
    protected $table = 'cities';
    protected $guarded = ['city_id'];
    protected $primaryKey = 'city_id';

    /**
     * Scope of country code
     * @param  [type] $query     [description]
     * @param  [type] $country_code [description]
     */
    public function scopeCountryCode($query, $country_code)
    {
        return $query->where('country_code', '=', $country_code);
    }

    /**
     * Scope of state code
     * @param  [type] $query      [description]
     * @param  [type] $state_code [description]
     * @return [type]             [description]
     */
    public function scopeStateCode($query, $state_code)
    {
        if (!empty($state_code)) {
            $query->where('state_code', '=', $state_code);
        }
        return $query;
    }

    /**
     * Scope of city name
     * @param  [type] $query      [description]
     * @param  [type] $city_name [description]
     * @return [type]             [description]
     */
    public function scopeCityName($query, $city_name)
    {
        return $query->where(app('db')->raw('LOWER(city_name)'), '=', mb_strtolower($city_name));
    }
}
