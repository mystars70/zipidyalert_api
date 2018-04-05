<?php
namespace App;

use DB;
use Auth;
use Mail;

class SendMail {
    protected $type;
    protected $to;
    protected $options;

    public function __construct($type, $to, $options)
    {
        $this->type = $type;
        $this->to = $to;
        $this->options = $options;
    }

    public function send() {
        $objDB = DB::table('email')->select('*')->Where('id', $this->type)->first();
        if ($objDB) {
            $template = explode('.', $objDB->template);
            Mail::send(['html' => 'mail.'.$template[0]], $this->options, function($message) use ($objDB) {
                    $message->to($this->to, 'Zipidy Alert')->subject($objDB->subject);
            });
            $this->count();
        }
    }

    public function count() {
        $objDB = DB::table('email')->select('*')->Where('id', $this->type)->first();
        if ($objDB) {
            $total = $objDB->total_sent;
            DB::table('email')->Where('id', $this->type)->update(['total_sent' => $total + 1]);
        }
    }
}