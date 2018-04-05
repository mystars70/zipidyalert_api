<?php
namespace App\DataPackage;

use App\Model\Business as BusinessModel;
use App\Model\ReplyMessages as ReplyMessagesModel;
use App\Model\UserReceiveMessage as UserReceiveMessageModel;
use App\Model\User as UserModel;

use App\FCM\Firebase;
use App\FCM\Push;

class MessagePackage extends DataPackage
{
    public static function getInstance()
    {
        if (self::$_instance === null) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * lấy data của reply message
     * @param  [type] $replyID [description]
     * @return [type]          [description]
     */
    public function getReplyMessageByID($replyID)
    {
        $mReplyMessage = ReplyMessagesModel::find($replyID);
        if ($mReplyMessage != null) {
            $user = UserModel::find($mReplyMessage->sender_id);
            // unset user data
            unset($user->user_id);
            unset($user->username);
            unset($user->password);
            unset($user->token_key);
            $mReplyMessage->sender = $user;
        }
        return $mReplyMessage;
    }

    /**
     * send message to business
     * @param  [User Setting Model] $mUserSetting    [description]
     * @param  [Messsage Model] $mMessage    [description]
     * @param  [Push] $messageContent [description]
     * @return [type]                 [description]
     */
    public function sendMessageToBusiness($mUserSetting, $mMessage, $messageContent)
    {
        $reAndroid = 0;
        $reWeb = 0;
        $reIOS = 0;

        if ($mUserSetting != null) {
            if (!empty($mUserSetting->fcm_id)) {
                $firebase = new Firebase();
                $response = $firebase->send($mUserSetting->fcm_id, $messageContent);
                $re = json_decode($response, true);
                if ($re['success'] == 1) {
                    $reAndroid = 1;
                }
            }

            if (!empty($mUserSetting->fcm_id_web)) {
                $firebase = new Firebase();
                $response = $firebase->send($mUserSetting->fcm_id_web, $messageContent);
                $re = json_decode($response, true);
                if ($re['success'] == 1) {
                    $reWeb = 1;
                }
            }

            if (!empty($mUserSetting->fcm_id_ios)) {
                $firebase = new Firebase();
                $response = $firebase->send($mUserSetting->fcm_id_ios, $messageContent);
                $re = json_decode($response, true);
                if ($re['success'] == 1) {
                    $reIOS = 1;
                }
            }

            if ($reAndroid == 1 || $reWeb == 1 || $reIOS == 1) {
                UserReceiveMessageModel::create([
                    'user_id' => $mUserSetting->user_id,
                    'message_id' => $mMessage->message_id,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
        }
    }

    /**
     * send notice to device
     * @param  [type] $mUserSetting   [description]
     * @param  [type] $messageContent [description]
     * @return [type]                 [description]
     */
    public function sendNoticeToDevice($mUserSetting, $messageContent)
    {
        $reAndroid = 0;
        $reWeb = 0;
        $reIOS = 0;

        if ($mUserSetting != null) {
            if (!empty($mUserSetting->fcm_id)) {
                $firebase = new Firebase();
                $response = $firebase->send($mUserSetting->fcm_id, $messageContent);
                $re = json_decode($response, true);
                if ($re['success'] == 1) {
                    $reAndroid = 1;
                }
            }

            if (!empty($mUserSetting->fcm_id_web)) {
                $firebase = new Firebase();
                $response = $firebase->send($mUserSetting->fcm_id_web, $messageContent);
                $re = json_decode($response, true);
                if ($re['success'] == 1) {
                    $reWeb = 1;
                }
            }

            if (!empty($mUserSetting->fcm_id_ios)) {
                $firebase = new Firebase();
                $response = $firebase->send($mUserSetting->fcm_id_ios, $messageContent);
                $re = json_decode($response, true);
                if ($re['success'] == 1) {
                    $reIOS = 1;
                }
            }
        }

        return ($reAndroid == 1 || $reWeb == 1 || $reIOS == 1);
    }
}
