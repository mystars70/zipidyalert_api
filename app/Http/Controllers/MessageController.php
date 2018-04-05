<?php
namespace App\Http\Controllers;

use Validator;
use Session;
use Hash;
use Illuminate\Http\Request;

use App\Http\Core\BaseController;
use App\Model\Messages as MessagesModel;
use App\Model\Business as BusinessModel;
use App\Model\UserReceiveMessage as UserReceiveMessageModel;
use App\Model\ReplyMessages as ReplyMessagesModel;
use App\Model\User as UserModel;
use App\DataPackage\BusinessPackage;
use App\DataPackage\MessagePackage;

use App\FCM\Firebase;
use App\FCM\Push;

class MessageController extends BaseController
{
    /**
     * get message detail
     * @param  Request $request      [description]
     * @param  [type]  $message_id [description]
     * @return [type]               [description]
     */
    public function detail(Request $request, $message_id)
    {
        $mMessage = MessagesModel::find($message_id);

        if ($mMessage != null) {
            // data form
            $dataForm = [
                'token' => $request->header(env('TOKEN_APP', 'Zipidy-Token')),
            ];

            // lấy user
            $mUser = UserModel::tokenKey($dataForm['token'])->actived()
                                ->get()->first();

            $mUserSender = UserModel::find($mMessage->sender_id);
            // unset user data
            // unset($mUserSender->user_id);
            unset($mUserSender->username);
            unset($mUserSender->password);
            unset($mUserSender->token_key);
            $mMessage->sender = $mUserSender;
            // get business
            $mMessage->business = BusinessPackage::getInstance()->getBusinessById($mMessage->business_id);
            // flag kiểm tra xem có là indirect | direct | manager | owner hay không
            // 1: owner | 2: manager | 3: direct | 4: indirect | 0: free user
            $isUserType = 0;
            if ($mUser->user_id == $mMessage->business->owner->user_id) {
                $isUserType = 1;
            }

            if ($isUserType == 0) {
                foreach ($mMessage->business->manager as $mn) {
                    if ($mUser->user_id == $mn->user_id) {
                        $isUserType = 2;
                    }
                }
            }

            if ($isUserType == 0) {
                foreach ($mMessage->business->direct as $mn) {
                    if ($mUser->user_id == $mn->user_id) {
                        $isUserType = 3;
                    }
                }
            }

            if ($isUserType == 0) {
                foreach ($mMessage->business->indirect as $mn) {
                    if ($mUser->user_id == $mn->user_id) {
                        $isUserType = 4;
                    }
                }
            }

            if ($mMessage->replyMessages != null) {
                $mReplyMessage = ReplyMessagesModel::messageID($mMessage->message_id);
                if (in_array($isUserType, [0, 3, 4])) {
                    $mReplyMessage = $mReplyMessage->senderID($mUser->user_id);
                }
                $mReplyMessage = $mReplyMessage->get();

                foreach ($mReplyMessage as $i => $reply) {
                    $user = UserModel::find($reply->sender_id);
                    // var_dump($user->user_id);
                    // unset user data
                    // unset($user->user_id);
                    unset($user->username);
                    unset($user->password);
                    unset($user->token_key);
                    $mReplyMessage[$i]->sender = $user;
                }
            }

            return $this->responseDataJson([
                'messages' => $mMessage,
                'replies' => $mReplyMessage,
                'isUserType' => $isUserType
            ]);
        }

        return $this->responseDataJson(['Bad Request'], false);
    }

    /**
     * reply message
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function reply(Request $request)
    {
        // Kiểm tra thông tin input
        $validator = app('validator')->make($request->all(), [
            'message_id' => 'required|numeric|exists:messages,message_id',
            'content' => 'required|min:1',
        ]);

        if (!$validator->fails()) {
            // data form
            $dataForm = [
                'token' => $request->header(env('TOKEN_APP', 'Zipidy-Token')),
                'message_id' => $request->input('message_id'),
                'content' => $request->input('content')
            ];

            // lấy user
            $mUser = UserModel::tokenKey($dataForm['token'])->actived()
                                ->get()->first();

            // lấy message
            $mMessage = MessagesModel::find($dataForm['message_id']);

            if ($mUser != null && $mMessage != null) {
                // get business
                $mBusiness = $mMessage->business;

                try {
                    app('db')->beginTransaction();
                    // reply message
                    $mReplyMessage = ReplyMessagesModel::create([
                        'detail' => $dataForm['content'],
                        'message_id' => $dataForm['message_id'],
                        'sender_id' => $mUser->user_id,
                        'sent_to' => $mMessage->sender_id,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);

                    $file = $request->file('photo');
                    if ($file != null) {
                        $image = $dataForm['message_id']. '_' . $mReplyMessage->reply_id . '_replymessage.' . $file->getClientOriginalExtension();
                        $file->move(env('DIR_UPLOAD_MESSAGE', 'upload/'), $image);

                        $mReplyMessage->image = $image;
                        $mReplyMessage->save();
                    }

                    $mReplyMessage = MessagePackage::getInstance()->getReplyMessageByID($mReplyMessage->reply_id);

                    // Preparing message content
                    $push = new Push();
                    $push->setImage(env('MESSAGE_IMAGE_URI', 'null') . $mReplyMessage->image);
                    $push->setPayload([
                        'team' => 'VNN',
                        'score' => '5.6',
                        'message' => [
                            'title' => $mBusiness->name,
                            'message_id' => $mReplyMessage->message_id,
                            'reply_id' => $mReplyMessage->reply_id,
                            'content' => $mReplyMessage->detail,
                            'type' => 'reply'
                        ]
                    ]);

                    // get owner of message, send notify owner
                    $mUserSendMessage = UserModel::find($mMessage->sender_id);
                    if ($mUserSendMessage != null && $mUserSendMessage->user_id != $mUser->user_id && !empty($mUserSendMessage->userSetting->fcm_id)) {
                        $firebase = new Firebase();
                        $response = $firebase->send($mUserSendMessage->userSetting->fcm_id, $push->getPush());
                    }

                    // get owner business, send notify to owner
                    $mUBusiness = $mBusiness->userBusiness()->userType([1])->get()->first();
                    $mBUser = UserModel::find($mUBusiness->user_id);
                    if ($mBUser != null && $mBUser->user_id != $mUserSendMessage->user_id && !empty($mBUser->userSetting->fcm_id)) {
                        $firebase = new Firebase();
                        $response = $firebase->send($mBUser->userSetting->fcm_id, $push->getPush());
                    }
                    if ($mBUser != null && $mBUser->user_id != $mUserSendMessage->user_id && !empty($mBUser->userSetting->fcm_id_web)) {
                        $firebase = new Firebase();
                        $response = $firebase->send($mBUser->userSetting->fcm_id_web, $push->getPush());
                    }
                    if ($mBUser != null && $mBUser->user_id != $mUserSendMessage->user_id && !empty($mBUser->userSetting->fcm_id_ios)) {
                        $firebase = new Firebase();
                        $response = $firebase->send($mBUser->userSetting->fcm_id_ios, $push->getPush());
                    }

                    app('db')->commit();
                    return $this->responseDataJson([
                        'reply' => $mReplyMessage
                    ]);
                } catch (Exception $e) {
                    app('db')->rollBack();
                    return $this->responseDataJson([$e->getMessage()], false);
                }
            }
        }

        return $this->responseDataJson(['Bad Request'], false);
    }
}
