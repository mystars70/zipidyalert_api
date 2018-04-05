<?php
namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class States extends Model
{
    protected $table = 'states';
    protected $guarded = ['state_id'];
    protected $primaryKey = 'state_id';

    /**
     * Scope of country id
     * @param  [type] $query     [description]
     * @param  [type] $token_key [description]
     */
    public function scopeCountryID($query, $country_id)
    {
        return $query->where('country_id', '=', $country_id);
    }
}
