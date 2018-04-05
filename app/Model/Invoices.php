<?php
namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Invoices extends Model
{
    protected $table = 'invoices';
    protected $guarded = ['invoice_id', 'user_id', 'business_id'];
    protected $primaryKey = 'invoice_id';

    public function businesses()
    {
        return $this->hasOne('App\Model\Business');
    }
}
