<?php
namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Countries extends Model
{
    protected $table = 'countries';
    protected $guarded = ['country_id'];
    protected $primaryKey = 'country_id';
}
