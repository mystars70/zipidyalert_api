<?php
namespace App\Http\Controllers;

use Validator;
use Session;
use Hash;
use Illuminate\Http\Request;

use App\Http\Core\BaseController;
use App\Model\User as UserModel;
use App\Model\UserSetting as UserSettingModel;

class AuthenticationController extends BaseController
{

    /**
     * Kiểm tra thông tin đăng nhập của thành viên
     * @param  Request $request  [description]
     * @return [type]            [description]
     */
    public function login(Request $request)
    {
        $validator = app('validator')->make($request->all(), [
            'username'  => 'required',
            'password'  => 'required',
            'token'  => 'required'
        ]);

        // Kiểm tra thông tin input
        if ($validator->fails()) {
            return $this->responseDataJson(['Login is failed'], false);
        }

        // data form
        $dataForm = [
            'username' => md5($request->input('username')),
            'email' => $request->input('username'),
            'password' => $request->input('password'),
            'token' => $request->input('token'),
            'device' => $request->input('device')
        ];

        // Kiểm tra thành viên với username này có tồn tại không?
        $mUser = UserModel::username($dataForm['username'])
                            ->email($dataForm['email'])->actived()->get()->first();
        // app('hash')->make($plainPassword);
        if (empty($mUser) || !app('hash')->check($dataForm['password'], $mUser->password)) {
            return $this->responseDataJson(['Invalid Username & Password'], false);
        }

        // Remove FCM ID from all user
        $mUserSetting = UserSettingModel::fcmID($dataForm['token'])->get();
        if ($mUserSetting != null) {
            foreach ($mUserSetting as $us) {
                $us->fcm_id = '';
                $us->fcm_id_ios = '';
                $us->save();
            }
        }

        // cập nhật token key cho member
        $mUser->token_key = $this->getTokenKey();
        $mUser->save();

        // update fcm id to user settings
        if ($dataForm['device'] == 'ios') {
            $mUser->userSetting->fcm_id_ios = $dataForm['token'];
        } else {
            $mUser->userSetting->fcm_id = $dataForm['token'];
        }
        $mUser->userSetting->save();

        $token_key = $mUser->token_key;
        $login_key = $mUser->username;
        // unset user data
        // unset($mUser->user_id);
        unset($mUser->username);
        unset($mUser->password);
        unset($mUser->token_key);

        return $this->responseDataJson([
            'user_info' => $mUser,
            'auth_info' => [
                'email' => $mUser->email,
                'token_key' => $token_key,
                'login_key' => $login_key
            ]
        ]);
    }

    public function logout(Request $request, $username)
    {
        // data form
        $dataForm = [
            'token' => $request->header(env('TOKEN_APP', 'Zipidy-Token')),
        ];

        // lấy user dựa vào thông tin input
        $mUser = UserModel::username($username)
                            ->tokenKey($dataForm['token'])->actived()
                            ->get()->first();

        if ($mUser != null) {
            $mUser->token_key = '';
            $mUser->save();
            // remove fcm id to user setting
            $mUser->userSetting->fcm_id = '';
            $mUser->userSetting->save();

            $re['data']['msg'] = ['OK'];
            return $this->responseDataJson([], true, $re);
        }

        // Kiểm tra thông tin input
        return $this->responseDataJson(['Failure'], false);
    }

    /**
     * Tạo token key.
     * @return return token token.
     */
    private static function getTokenKey()
    {
        $key = bin2hex(openssl_random_pseudo_bytes(32));
        return rtrim(strtr(base64_encode($key), '+/', '-_'), '=');
    }
}
