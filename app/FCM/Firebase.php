<?php
namespace App\FCM;

class Firebase
{

    // sending push message to single user by firebase reg id
    public function send($to, $message)
    {
        $msg = $message['data']['payload']['message'];
        $fields = array(
            'to' => $to,
            'data' => $message,
            'notification' => [
                "body" => $msg['content'],
                "title" => $msg['title']
            ]
        );
        return $this->sendPushNotification($fields);
    }

    // Sending message to a topic by topic name
    public function sendToTopic($to, $message)
    {
        $msg = $message['data']['payload']['message'];
        $fields = array(
            'to' => '/topics/' . $to,
            'data' => $message,
            'notification' => [
                "body" => $msg['content'],
                "title" => $msg['title']
            ]
        );
        return $this->sendPushNotification($fields);
    }

    // sending push message to multiple users by firebase registration ids
    public function sendMultiple($registration_ids, $message)
    {
        $msg = $message['data']['payload']['message'];
        $fields = array(
            'to' => $registration_ids,
            'data' => $message,
            'notification' => [
                "body" => $msg['content'],
                "title" => $msg['title']
            ]
        );

        return $this->sendPushNotification($fields);
    }

    // function makes curl request to firebase servers
    private function sendPushNotification($fields)
    {
        // Set POST variables
        $url = env('CM_API', '');

        $headers = array(
            'Authorization: key=' . env('CM_KEY', ''),
            'Content-Type: application/json'
        );
        // Open connection
        $ch = curl_init();

        // Set the url, number of POST vars, POST data
        curl_setopt($ch, CURLOPT_URL, $url);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Disabling SSL Certificate support temporarly
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));

        // Execute post
        $result = curl_exec($ch);
        if ($result === false) {
            // die('Curl failed: ' . curl_error($ch));
        }

        // Close connection
        curl_close($ch);

        return $result;
    }
}
