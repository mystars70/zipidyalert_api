<?php
namespace App\FCM;

class Push
{
    // private $image = 'http://35.185.226.199/api/public/upload/av.jpg';
    private $image = '';
    // push message payload
    private $data;

    public function __construct()
    {
    }

    public function setImage($imageUrl)
    {
        $this->image = $imageUrl;
    }

    public function setPayload($data)
    {
        $this->data = $data;
    }

    public function getPush()
    {
        $res = array();
        $res['data']['image'] = $this->image;
        $res['data']['payload'] = $this->data;
        $res['data']['timestamp'] = date('Y-m-d H:i:s');
        return $res;
    }
}
