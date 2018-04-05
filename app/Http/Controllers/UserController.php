<?php
namespace App\Http\Controllers;

use Validator;
use Session;
use Hash;
use Illuminate\Http\Request;

use App\Http\Core\BaseController;
use App\Model\User as UserModel;
use App\Model\UserSetting as UserSettingModel;
use App\Model\Messages as MessagesModel;
use App\Model\Business as BusinessModel;
use App\Model\UserBusinesses as UserBusinessesModel;
use App\Model\UserReceiveMessage as UserReceiveMessageModel;
use App\Model\Countries as CountriesModel;
use App\Model\States as StatesModel;
use App\Model\Cities as CitiesModel;

use Swift_Mailer;
use Swift_SmtpTransport;
use Swift_Message;

use App\DataPackage\MessagePackage;

use App\FCM\Firebase;
use App\FCM\Push;

use DB;

class UserController extends BaseController
{
    /**
     * check user is have business or not
     * @param  Request $request  [description]
     * @param  [type]  $username [description]
     * @return boolean           [description]
     */
    public function isHaveBusiness(Request $request, $username)
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
            $mUserBusiness = UserBusinessesModel::userID($mUser->user_id)
                                                ->userType([1])->status(1)
                                                ->get()->first();
            if ($mUserBusiness == null) {
                return $this->responseDataJson([
                    'is_owner' => false,
                    'business_id' => null
                ]);
            }

            // unset($mUser->user_id);
            unset($mUser->username);
            unset($mUser->password);
            unset($mUser->token_key);

            return $this->responseDataJson([
                'user_info' => $mUser,
                'is_owner' => true,
                'business_id' => $mUserBusiness->business->business_id
            ]);
        }

        return $this->responseDataJson(['Bad Request'], false);
    }

    /**
     * accept invitation
     * @param Request $request     [description]
     */
    public function invitationAccept(Request $request)
    {
        // Kiểm tra thông tin input
        $validator = app('validator')->make($request->all(), [
            'type' => 'required|numeric',
            'bid'  => 'required|numeric|exists:businesses,business_id'
        ]);

        if (!$validator->fails()) {
            $dataForm = [
                'token' => $request->header(env('TOKEN_APP', 'Zipidy-Token')),
                'type' => $request->input('type'),
                'bid' => $request->input('bid')
            ];

            // lấy user dựa vào thông tin input
            $mUser = UserModel::tokenKey($dataForm['token'])
                                ->actived()->get()->first();

            // get business
            $mBusiness = BusinessModel::find($dataForm['bid']);

            if ($mUser != null && $mBusiness) {
                // get uset is unset
                $isBelong = $mBusiness->userBusiness()
                            ->userID($mUser->user_id)
                            ->status(2)
                            ->userType([$dataForm['type']])->get()->first();
                if ($isBelong != null) {
                    $mUserBusiness = $mBusiness->userBusiness()
                                ->userID($mUser->user_id)
                                ->status(2)
                                ->userType([$dataForm['type']])->delete();

                    UserBusinessesModel::create([
                        'user_id' => $mUser->user_id,
                        'user_type' => $dataForm['type'],
                        'status' => 1,
                        'business_id' => $mBusiness->business_id
                    ]);

                    $re['data']['msg'] = ['OK'];
                    return $this->responseDataJson([], true, $re);
                }
            }
        }

        return $this->responseDataJson(['Bad Request'], false);
    }

    /**
     * deny invitation
     * @param Request $request     [description]
     */
    public function invitationDeny(Request $request)
    {
        // Kiểm tra thông tin input
        $validator = app('validator')->make($request->all(), [
            'type' => 'required|numeric',
            'bid'  => 'required|numeric|exists:businesses,business_id'
        ]);

        if (!$validator->fails()) {
            $dataForm = [
                'token' => $request->header(env('TOKEN_APP', 'Zipidy-Token')),
                'type' => $request->input('type'),
                'bid' => $request->input('bid')
            ];

            // lấy user dựa vào thông tin input
            $mUser = UserModel::tokenKey($dataForm['token'])
                                ->actived()->get()->first();

            // get business
            $mBusiness = BusinessModel::find($dataForm['bid']);

            if ($mUser != null && $mBusiness) {
                // get uset is unset
                $isBelong = $mBusiness->userBusiness()
                            ->userID($mUser->user_id)
                            ->status(2)
                            ->userType([$dataForm['type']])->get()->first();
                if ($isBelong != null) {
                    $mUserBusiness = $mBusiness->userBusiness()
                                ->userID($mUser->user_id)
                                ->status(2)
                                ->userType([$dataForm['type']])->delete();
                    $re['data']['msg'] = ['OK'];
                    return $this->responseDataJson([], true, $re);
                }
            }
        }

        return $this->responseDataJson(['Bad Request'], false);
    }

    /**
     * get invitation join to other business
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function invitation(Request $request, $username)
    {
        // data form
        $dataForm = [
            'token' => $request->header(env('TOKEN_APP', 'Zipidy-Token'))
        ];
        // lấy user dựa vào thông tin input
        $mUser = UserModel::username($username)
                            ->tokenKey($dataForm['token'])->actived()
                            ->get()->first();

        // get user business
        $mUserBusiness = UserBusinessesModel::userID($mUser->user_id)->status(2)->get();
        if (!empty($mUserBusiness)) {
            foreach ($mUserBusiness as &$dt) {
                $dt->business;
            }
        }

        return $this->responseDataJson($mUserBusiness);
    }

    /**
     * Share email
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function forgotPassword(Request $request)
    {
        $validator = app('validator')->make($request->all(), [
            'email' => 'required|max:100|email'
        ]);

        // Kiểm tra thông tin input
        if ($validator->fails()) {
            return $this->responseDataJson(['Bad Request'], false);
        }

        try {
            // data form
            $dataForm = [
                'email' => $request->input('email')
            ];

            // lấy user dựa vào thông tin input
            $mUser = UserModel::email($dataForm['email'])
                                ->actived()->get()->first();

            if ($mUser == null) {
                return $this->responseDataJson(['Bad Request'], false);
            }

            $token = $mUser->secret_key;
            $email = $dataForm['email'];
            $template = DB::table('email')->select('*')->Where('id', 7)->first();
            if ($template) {
                $template_text = file_get_contents('/var/www/html/git/zipidy-web/resources/views/mail/' . $template->template);
                file_put_contents('/var/www/html/api/resources/views/mail/' . $template->template, $template_text);
            } else {
                return $this->responseDataJson(['Bad Request'], false);
            }


            // $newPassword = $this->generateRandomString(5);
            // // Update new password
            // $mUser->password = app('hash')->make($newPassword);
            // $mUser->save();

            // Create the Transport
            $transport = (new Swift_SmtpTransport('smtp.gmail.com', 465, 'ssl'))
              ->setUsername(env('EMAIL_USER', ''))
              ->setPassword(env('EMAIL_PASS', ''));

            // Create the Mailer using your created Transport
            $mailer = new Swift_Mailer($transport);
            // Create a message
            $message = new Swift_Message($template->subject);
            $message = $message->setFrom(['zipidy.sp@gmail.com' => 'Zipidy Alert']);
            $message->setTo([$dataForm['email']])
                ->setBody(view('mail.changePassword')->with(['email' => $email,'token' => $token])->render())
                ->setContentType('text/html');
            // Send the message
            $result = $mailer->send($message);
            $re['data']['msg'] = ['OK'];
            return $this->responseDataJson([], true, $re);
        } catch (Exception $e) {
            return $this->responseDataJson(['Bad Request'], false);
        }
    }

    /**
     * Update profile
     * @param  Request $request  [description]
     * @return [type]            [description]
     */
    public function updateProfile(Request $request, $username)
    {
        // data form
        $dataForm = [
            'token' => $request->header(env('TOKEN_APP', 'Zipidy-Token'))
        ];
        // lấy user dựa vào thông tin input
        $mUser = UserModel::username($username)
                            ->tokenKey($dataForm['token'])->actived()
                            ->get()->first();

        if ($mUser == null) {
            return $this->responseDataJson(['Bad Request'], false);
        }

        $validator = app('validator')->make($request->all(), [
            'country' => 'required|integer',
            'state' => 'integer',
            'city' => 'required|min:1|max:100',
            'zipcode' => 'integer|min:-2147483648|max:2147483647',
            'email' => 'required|max:100|email|unique:users,email,' . $mUser->user_id . ',user_id',
            'phone' => 'min:1|max:32',
            'fname' => 'required|min:1|max:32',
            'lname' => 'required|min:1|max:32'
        ]);

        // Kiểm tra thông tin input
        if ($validator->fails()) {
            return $this->responseDataJson($this->getValidationError($validator), false);
        }

        if ($request->input('password') != "") {
            $validator = app('validator')->make($request->all(), [
                'password' => 'required|min:1|max:32',
                'cpassword' => 'required|min:1|max:32|same:password'
            ]);

            // Kiểm tra password
            if ($validator->fails()) {
                return $this->responseDataJson($this->getValidationError($validator), false);
            }
        }

        try {
            // data form
            $dataForm = [
                'token' => $request->header(env('TOKEN_APP', 'Zipidy-Token')),
                'country' => $request->input('country'),
                'state' => $request->input('state'),
                'city' => $request->input('city'),
                'zipcode' => $request->input('zipcode'),
                'email' => $request->input('email'),
                'phone' => $request->input('phone'),
                'fname' => $request->input('fname'),
                'lname' => $request->input('lname'),
                'password' => app('hash')->make($request->input('password'))
            ];

            // get country
            $mCountry = CountriesModel::find($dataForm['country']);
            // get state
            $mState = StatesModel::find($dataForm['state']);
            // get city
            $mCity = CitiesModel::countryCode($mCountry->country_code)
                                    ->cityName($dataForm['city']);
            if ($mState != null) {
                $mCity->stateCode($mState->state_code);
            }
            $mCity = $mCity->get()->first();

            app('db')->beginTransaction();

            // update user
            $mUser->country_id = $dataForm['country'];
            $mUser->state_id = $dataForm['state'];
            $mUser->city_id = $mCity == null ? 0 : $mCity->city_id;
            $mUser->city_name = $mCity == null ? $dataForm['city'] : $mCity->city_name;
            $mUser->zipcode = $dataForm['zipcode'];
            $mUser->email = $dataForm['email'];
            $mUser->username = md5($dataForm['email']);
            $mUser->firstname = $dataForm['fname'];
            $mUser->lastname = $dataForm['lname'];

            if ($request->input('password') != "") {
                $mUser->password = $dataForm['password'];
            }

            $mUser->save();

            app('db')->commit();

            $re['data']['msg'] = ['OK'];
            return $this->responseDataJson([], true, $re);
        } catch (Exception $e) {
            app('db')->rollBack();
        }

        return $this->responseDataJson(['Bad Request'], false);
    }

    /**
     * Get user info
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function profile(Request $request, $username)
    {
        // data form
        $dataForm = [
            'token' => $request->header(env('TOKEN_APP', 'Zipidy-Token'))
        ];
        // lấy user dựa vào thông tin input
        $mUser = UserModel::username($username)
                            ->tokenKey($dataForm['token'])->actived()
                            ->get()->first();

        if ($mUser != null) {
            // unset($mUser->user_id);
            unset($mUser->username);
            unset($mUser->password);
            unset($mUser->token_key);

            return $this->responseDataJson([
                'info' => $mUser
            ]);
        }

        return $this->responseDataJson(['Bad Request'], false);
    }

    /**
     * Share email
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function shareEmail(Request $request)
    {
        $validator = app('validator')->make($request->all(), [
            'bid' => 'required|integer',
            'email' => 'required|max:100|email'
        ]);

        // Kiểm tra thông tin input
        if ($validator->fails()) {
            return $this->responseDataJson(['Bad Request'], false);
        }

        try {
            // data form
            $dataForm = [
                'bid' => $request->input('bid'),
                'email' => $request->input('email')
            ];

            // lấy user dựa vào thông tin input
            $mUser = UserModel::email($dataForm['email'])
                                ->actived()->get()->first();

            // Create the Transport
            $transport = (new Swift_SmtpTransport('smtp.gmail.com', 465, 'ssl'))
              ->setUsername(env('EMAIL_USER', ''))
              ->setPassword(env('EMAIL_PASS', ''));

            // Create the Mailer using your created Transport
            $mailer = new Swift_Mailer($transport);

            // Create a message
            $message = new Swift_Message('Zipidy Alert');
            $message = $message->setFrom(['zipidy.sp@gmail.com' => 'Zipidy Alert']);

            if ($mUser == null) {
                    $message->setTo([$dataForm['email']])
                    ->setBody('Here is the content register user');
            } else {
                    $message->setTo([$dataForm['email']])
                    ->setBody('Here is the content register indirect user');
            }
            // Send the message
            $result = $mailer->send($message);

            $re['data']['msg'] = ['OK'];
            return $this->responseDataJson([], true, $re);
        } catch (Exception $e) {
            return $this->responseDataJson(['Bad Request'], false);
        }
    }

    /**
     * Đăng ký thành viên
     * @param  Request $request  [description]
     * @return [type]            [description]
     */
    public function registerBusiness(Request $request)
    {
        $validator = app('validator')->make($request->all(), [
            'country' => 'required|integer',
            'state' => 'integer',
            'city' => 'required|min:1|max:100',
            'zipcode' => 'integer|min:-2147483648|max:2147483647',
            'email' => 'required|max:100|email|unique:users',
            'bemail' => 'required|max:100|email|unique:businesses,email',
            'name' => 'min:1|max:45',
            'address' => 'required|min:1|max:200',
            'phone' => 'min:1|max:32',
            'fname' => 'required|min:1|max:32',
            'lname' => 'required|min:1|max:32',
            'password' => 'required|min:1|max:32',
            'cpassword' => 'required|min:1|max:32|same:password',
            'blat'  => 'required|numeric',
            'blon'  => 'required|numeric',
            'lat'  => 'required|numeric',
            'lon'  => 'required|numeric',
            'token'  => 'required'
        ]);

        // Kiểm tra thông tin input
        if ($validator->fails()) {
            return $this->responseDataJson($this->getValidationError($validator), false);
        }

        try {
            // data form
            $dataForm = [
                'country' => $request->input('country'),
                'state' => $request->input('state'),
                'city' => $request->input('city'),
                'zipcode' => $request->input('zipcode'),
                'email' => $request->input('email'),
                'bemail' => $request->input('bemail'),
                'phone' => $request->input('phone'),
                'fname' => $request->input('fname'),
                'lname' => $request->input('lname'),
                'name' => $request->input('name'),
                'address' => $request->input('address'),
                'password' => app('hash')->make($request->input('password')),
                'blat' => $request->input('blat'),
                'blon' => $request->input('blon'),
                'lat' => $request->input('lat'),
                'lon' => $request->input('lon'),
                'token' => $request->input('token')
            ];

            // get country
            $mCountry = CountriesModel::find($dataForm['country']);
            // get state
            $mState = StatesModel::find($dataForm['state']);
            // get city
            $mCity = CitiesModel::countryCode($mCountry->country_code)
                                    ->cityName($dataForm['city']);
            if ($mState != null) {
                $mCity->stateCode($mState->state_code);
            }
            $mCity = $mCity->get()->first();

            app('db')->beginTransaction();

            // đăng ký user
            $mUser = UserModel::create([
                'country_id' => $dataForm['country'],
                'state_id' => $dataForm['state'],
                'city_id' => $mCity == null ? 0 : $mCity->city_id,
                'city_name' => $mCity == null ? $dataForm['city'] : $mCity->city_name,
                'zipcode' => $dataForm['zipcode'],
                'email' => $dataForm['email'],
                'phone' => $dataForm['phone'],
                'firstname' => $dataForm['fname'],
                'lastname' => $dataForm['lname'],
                'username' => md5($dataForm['email']),
                'password' => $dataForm['password'],
                'lat' => $dataForm['lat'],
                'lon' => $dataForm['lon'],
                'token_key' => $this->getTokenKey(),
                'status' => 1
            ]);

            // Remove FCM ID from all user
            $mUserSettings = UserSettingModel::fcmID($dataForm['token'])->get();
            if ($mUserSettings != null) {
                foreach ($mUserSettings as $us) {
                    $us->fcm_id = '';
                    $us->save();
                }
            }

            // setting user
            $mUserSetting = $mUser->userSetting()->save(new UserSettingModel([
                'radius' => env('CONFIG_USER_RADIUS', 5),
                'notification' => 1,
                'notification_time' => date('Y-m-d H:i:s'),
                'fcm_id' => $dataForm['token']
            ]));

            // Save business
            $mBusiness = BusinessModel::create([
                'name' => $dataForm['name'],
                'email' => $dataForm['bemail'],
                'address' => $dataForm['address'],
                'country_id' => $dataForm['country'],
                'state_id' => $dataForm['state'],
                'city_id' => $mCity == null ? 0 : $mCity->city_id,
                'city_name' => $mCity == null ? $dataForm['city'] : $mCity->city_name,
                'zipcode' => $dataForm['zipcode'],
                'lat' => $dataForm['blat'],
                'lon' => $dataForm['blon'],
                'status' => 0
            ]);

            // Save business user
            UserBusinessesModel::create([
                'user_id' => $mUser->user_id,
                'user_type' => 1,
                'status' => 1,
                'business_id' => $mBusiness->business_id
            ]);

            app('db')->commit();

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
        } catch (Exception $e) {
            app('db')->rollBack();
        }

        return $this->responseDataJson(['Bad Request'], false);
    }

    /**
     * Đăng ký thành viên
     * @param  Request $request  [description]
     * @return [type]            [description]
     */
    public function register(Request $request)
    {
        $validator = app('validator')->make($request->all(), [
            'country' => 'required|integer',
            'state' => 'integer',
            'city' => 'required|min:1|max:100',
            'zipcode' => 'integer|min:-2147483648|max:2147483647',
            'email' => 'required|max:100|email|unique:users',
            'phone' => 'min:1|max:32',
            'fname' => 'required|min:1|max:32',
            'lname' => 'required|min:1|max:32',
            'password' => 'required|min:1|max:32',
            'cpassword' => 'required|min:1|max:32|same:password',
            'lat'  => 'required|numeric',
            'lon'  => 'required|numeric',
            'token'  => 'required'
        ]);

        // Kiểm tra thông tin input
        if ($validator->fails()) {
            return $this->responseDataJson($this->getValidationError($validator), false);
        }

        try {
            // data form
            $dataForm = [
                'country' => $request->input('country'),
                'state' => $request->input('state'),
                'city' => $request->input('city'),
                'zipcode' => $request->input('zipcode'),
                'email' => $request->input('email'),
                'phone' => $request->input('phone'),
                'fname' => $request->input('fname'),
                'lname' => $request->input('lname'),
                'password' => app('hash')->make($request->input('password')),
                'lat' => $request->input('lat'),
                'lon' => $request->input('lon'),
                'token' => $request->input('token')
            ];

            // get country
            $mCountry = CountriesModel::find($dataForm['country']);
            // get state
            $mState = StatesModel::find($dataForm['state']);
            // get city
            $mCity = CitiesModel::countryCode($mCountry->country_code)
                                    ->cityName($dataForm['city']);
            if ($mState != null) {
                $mCity->stateCode($mState->state_code);
            }
            $mCity = $mCity->get()->first();

            app('db')->beginTransaction();

            // đăng ký user
            $mUser = UserModel::create([
                'country_id' => $dataForm['country'],
                'state_id' => $dataForm['state'],
                'city_id' => $mCity == null ? 0 : $mCity->city_id,
                'city_name' => $mCity == null ? $dataForm['city'] : $mCity->city_name,
                'zipcode' => $dataForm['zipcode'],
                'email' => $dataForm['email'],
                'username' => md5($dataForm['email']),
                'phone' => $dataForm['phone'],
                'firstname' => $dataForm['fname'],
                'lastname' => $dataForm['lname'],
                'password' => $dataForm['password'],
                'lat' => $dataForm['lat'],
                'lon' => $dataForm['lon'],
                'token_key' => $this->getTokenKey(),
                'status' => 1
            ]);

            // Remove FCM ID from all user
            $mUserSettings = UserSettingModel::fcmID($dataForm['token'])->get();
            if ($mUserSettings != null) {
                foreach ($mUserSettings as $us) {
                    $us->fcm_id = '';
                    $us->save();
                }
            }

            // setting user
            $mUserSetting = $mUser->userSetting()->save(new UserSettingModel([
                'radius' => env('CONFIG_USER_RADIUS', 5),
                'notification' => 1,
                'notification_time' => date('Y-m-d H:i:s'),
                'fcm_id' => $dataForm['token']
            ]));

            app('db')->commit();

            $token_key = $mUser->token_key;
            $login_key = $mUser->username;
            // unset user data
            // unset($mUser->user_id);
            unset($mUser->username);
            unset($mUser->password);
            unset($mUser->token_key);

            // send email register
            // Create the Transport
            $transport = (new Swift_SmtpTransport('smtp.gmail.com', 465, 'ssl'))
              ->setUsername(env('EMAIL_USER', ''))
              ->setPassword(env('EMAIL_PASS', ''));

            // Create the Mailer using your created Transport
            $mailer = new Swift_Mailer($transport);

            // Create a message
            $message = new Swift_Message('Zipidy Alert');
            $message = $message->setFrom(['zipidy.sp@gmail.com' => 'Zipidy Alert']);
            $message->setTo([$mUser->email])
                    ->setBody('Here is the content active user');
            // Send the message
            $result = $mailer->send($message);

            return $this->responseDataJson([
                'user_info' => $mUser,
                'auth_info' => [
                    'email' => $mUser->email,
                    'token_key' => $token_key,
                    'login_key' => $login_key
                ]
            ]);
        } catch (Exception $e) {
            app('db')->rollBack();
        }

        return $this->responseDataJson(['Bad Request'], false);
    }

    /**
     * Update location user
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function trackLatlon(Request $request, $username)
    {
        // Kiểm tra thông tin input
        $validator = app('validator')->make($request->all(), [
            'lat'  => 'required|numeric',
            'lon'  => 'required|numeric'
        ]);

        if (!$validator->fails()) {
            // data form
            $dataForm = [
                'token' => $request->header(env('TOKEN_APP', 'Zipidy-Token')),
                'lat' => $request->input('lat'),
                'lon' => $request->input('lon')
            ];
            // lấy user dựa vào thông tin input
            $mUser = UserModel::username($username)
                                ->tokenKey($dataForm['token'])->actived()
                                ->get()->first();

            if ($mUser != null) {
                $mUser->lat = $dataForm['lat'];
                $mUser->lon = $dataForm['lon'];
                $mUser->save();

                return $this->responseDataJson([
                    'lat' => $mUser->lat,
                    'lon' => $mUser->lon
                ]);
            }
        }

        return $this->responseDataJson(['Bad Request'], false);
    }

    /**
     * send message to busiess of user paid
     * @param  Request $request  [description]
     * @param  [type]  $username [description]
     * @return [type]            [description]
     */
    public function sendMessageBusiness(Request $request, $username)
    {
        // Kiểm tra thông tin input
        $validator = app('validator')->make($request->all(), [
            'type' => 'required|numeric',
            'content' => 'required|min:1|max:1000',
            'bid'  => 'required|numeric|exists:businesses,business_id'
        ]);

        if (!$validator->fails()) {
            // data form
            $dataForm = [
                'token' => $request->header(env('TOKEN_APP', 'Zipidy-Token')),
                'type' => $request->input('type'),
                'content' => $request->input('content'),
                'bid' => $request->input('bid')
            ];
            // lấy user dựa vào thông tin input
            $mUser = UserModel::username($username)
                                ->tokenKey($dataForm['token'])->actived()
                                ->get()->first();


            if ($mUser != null) {
                try {
                    app('db')->beginTransaction();

                    // register message
                    $mMessage = MessagesModel::create([
                        'title' => '-',
                        'detail' => $dataForm['content'],
                        'sender_id' => $mUser->user_id,
                        'message_type' => $dataForm['type'],
                        'business_id' => $dataForm['bid']
                    ]);

                    $mMessage = MessagesModel::find($mMessage['message_id']);
                    $mMessage->business;

                    $userBelongsBusiness = [];
                    $mBusiness = BusinessModel::find($dataForm['bid']);

                    // Preparing message content
                    $push = new Push();
                    $push->setPayload([
                        'team' => 'VNN',
                        'score' => '5.6',
                        'message' => [
                            'title' => $mBusiness->name,
                            'message_id' => $mMessage->message_id,
                            'content' => $mMessage->detail,
                            'reply_id' => 0,
                            'type' => 'message'
                        ]
                    ]);
                    $messageContent = $push->getPush();

                    // send message to direct user | indirect user | owner user
                    foreach ($mBusiness->userBusiness()->get() as $buser) {
                        // set user belong to business, we not send message again
                        $userBelongsBusiness[] = $buser->user_id;
                        $mBUser = UserModel::find($buser->user_id);

                        // check user not is sender | not indirect
                        if ($mUser->user_id != $buser->user_id && $buser->user_type != 4) {
                            $reAndroid = 0;
                            $reWeb = 0;
                            $reIOS = 0;
                            if (!empty($mBUser->userSetting->fcm_id)) {
                                $firebase = new Firebase();
                                $response = $firebase->send($mBUser->userSetting->fcm_id, $messageContent);
                                $re = json_decode($response, true);
                                if ($re['success'] == 1) {
                                    $reAndroid = 1;
                                }
                            }

                            if (!empty($mBUser->userSetting->fcm_id_web)) {
                                $firebase = new Firebase();
                                $response = $firebase->send($mBUser->userSetting->fcm_id_web, $messageContent);
                                $re = json_decode($response, true);
                                if ($re['success'] == 1) {
                                    $reWeb = 1;
                                }
                            }

                            if (!empty($mBUser->userSetting->fcm_id_ios)) {
                                $firebase = new Firebase();
                                $response = $firebase->send($mBUser->userSetting->fcm_id_ios, $messageContent);
                                $re = json_decode($response, true);
                                if ($re['success'] == 1) {
                                    $reIOS = 1;
                                }
                            }

                            if ($reAndroid == 1 || $reWeb == 1 || $reIOS == 1) {
                                UserReceiveMessageModel::create([
                                    'user_id' => $mBUser->user_id,
                                    'message_id' => $mMessage->message_id,
                                    'created_at' => date('Y-m-d H:i:s')
                                ]);
                            }
                        }

                        // check user not is sender | not indirect
                        if ($dataForm['type'] == 1 && $mUser->user_id != $buser->user_id && $buser->user_type == 4) {
                            $reAndroid = 0;
                            $reWeb = 0;
                            $reIOS = 0;

                            if (!empty($mBUser->userSetting->fcm_id)) {
                                $firebase = new Firebase();
                                $response = $firebase->send($mBUser->userSetting->fcm_id, $messageContent);
                                $re = json_decode($response, true);
                                if ($re['success'] == 1) {
                                    $reAndroid = 1;
                                }
                            }

                            if (!empty($mBUser->userSetting->fcm_id_web)) {
                                $firebase = new Firebase();
                                $response = $firebase->send($mBUser->userSetting->fcm_id_web, $messageContent);
                                $re = json_decode($response, true);
                                if ($re['success'] == 1) {
                                    $reWeb = 1;
                                }
                            }

                            if (!empty($mBUser->userSetting->fcm_id_ios)) {
                                $firebase = new Firebase();
                                $response = $firebase->send($mBUser->userSetting->fcm_id_ios, $messageContent);
                                $re = json_decode($response, true);
                                if ($re['success'] == 1) {
                                    $reIOS = 1;
                                }
                            }

                            if ($reAndroid == 1 || $reWeb == 1 || $reIOS == 1) {
                                UserReceiveMessageModel::create([
                                    'user_id' => $mBUser->user_id,
                                    'message_id' => $mMessage->message_id,
                                    'created_at' => date('Y-m-d H:i:s')
                                ]);
                            }
                        }
                    }

                    // only send broacast
                    if ($dataForm['type'] == 1) {
                        $mUserInRange = UserModel::userInRange($mBusiness)->get();
                        // send message to free user
                        foreach ($mUserInRange as $user) {
                            if (!in_array($user->user_id, $userBelongsBusiness)) {
                                $reAndroid = 0;
                                $reWeb = 0;
                                $reIOS = 0;

                                if (!empty($user->userSetting->fcm_id)) {
                                    $firebase = new Firebase();
                                    $response = $firebase->send($user->userSetting->fcm_id, $messageContent);
                                    $re = json_decode($response, true);
                                    if ($re['success'] == 1) {
                                        $reAndroid = 1;
                                    }
                                }

                                if (!empty($user->userSetting->fcm_id_web)) {
                                    $firebase = new Firebase();
                                    $response = $firebase->send($user->userSetting->fcm_id_web, $messageContent);
                                    $re = json_decode($response, true);
                                    if ($re['success'] == 1) {
                                        $reWeb = 1;
                                    }
                                }

                                if (!empty($user->userSetting->fcm_id_ios)) {
                                    $firebase = new Firebase();
                                    $response = $firebase->send($user->userSetting->fcm_id_ios, $messageContent);
                                    $re = json_decode($response, true);
                                    if ($re['success'] == 1) {
                                        $reIOS = 1;
                                    }
                                }

                                if ($reAndroid == 1 || $reWeb == 1 || $reIOS == 1) {
                                    UserReceiveMessageModel::create([
                                        'user_id' => $user->user_id,
                                        'message_id' => $mMessage->message_id,
                                        'created_at' => date('Y-m-d H:i:s')
                                    ]);
                                }
                            }
                        }
                    }

                    app('db')->commit();
                    return $this->responseDataJson([$mMessage->toArray()]);
                } catch (Exception $e) {
                    app('db')->rollBack();
                    return $this->responseDataJson([$e->getMessage()], false);
                }
            }
        }
        // return $this->responseDataJson($this->getValidationError($validator), false);
        return $this->responseDataJson(['Bad Request'], false);
    }

    /**
     * get all data for main activity
     * - reeceive messages
     * - total user in city
     * - total business in city
     * @param  Request $request  [description]
     * @param  [type]  $username [description]
     * @return [type]            [description]
     */
    public function userDataPackage(Request $request, $username)
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
            // get country
            $mUser->country = CountriesModel::find($mUser->country_id);
            // get state
            $mUser->state = StatesModel::find($mUser->state_id);

            // flag check user have a business or not
            $isOwner = false;
            // get message
            $messagesData = [];
            $mUserReceiveMessage = UserReceiveMessageModel::userID($mUser->user_id)->orderBy('created_at', 'desc')->limit(30)->offset(0)->get();
            foreach ($mUserReceiveMessage as $urm) {
                $message = $urm->message;
                $user = UserModel::find($message->sender_id);
                // unset user data
                // unset($user->user_id);
                unset($user->username);
                unset($user->password);
                unset($user->token_key);
                $message->sender = $user;

                $business = BusinessModel::find($message->business_id);
                // get country
                $business->country = CountriesModel::find($business->country_id);
                // get state
                $business->state = StatesModel::find($business->state_id);
                $message->business = $business;
                $messagesData[] = $urm->message;
            }

            // get business belong to user
            $businessesData = [];
            $mUserBusiness = UserBusinessesModel::userID($mUser->user_id)->status(1)->get();
            foreach ($mUserBusiness as $ub) {
                $business = $ub->business;
                // get country
                $business->country = CountriesModel::find($business->country_id);
                // get state
                $business->state = StatesModel::find($business->state_id);

                // get user belong business
                $owner = null;
                $manager = [];
                $direct = [];
                $indirect = [];
                foreach ($business->userBusiness()->get() as $ub) {
                    $user = UserModel::find($ub->user_id);
                    // unset user data
                    // unset($user->user_id);
                    unset($user->username);
                    unset($user->password);
                    unset($user->token_key);

                    switch ($ub->user_type) {
                        case 1:
                            $owner = $user;
                            if ($owner->user_id == $mUser->user_id) {
                                $isOwner = true;
                            }
                            break;
                        case 2:
                            $manager[] = $user;
                            break;
                        case 3:
                            $direct[] = $user;
                            break;
                        case 4:
                            $indirect[] = $user;
                            break;
                        default:
                            # code...
                            break;
                    }
                }
                $business->owner = $owner;
                $business->manager = $manager;
                $business->direct = $direct;
                $business->indirect = $indirect;
                $businessesData[] = $business;
            }

            $mUserBusiness = UserBusinessesModel::userID($mUser->user_id)->userType([1])->get()->first();

            // unset user data
            // unset($mUser->user_id);
            unset($mUser->username);
            unset($mUser->password);
            unset($mUser->token_key);

            return $this->responseDataJson([
                'messages' => $messagesData,
                'businesses' => $businessesData,
                'info' => $mUser,
                'isOwner' => $isOwner,
                'totalUser' => UserModel::cityID($mUser->city_id)->count(),
                'totalBusiness' => BusinessModel::cityID($mUser->city_id)->count(),
                'totalNotice' => UserBusinessesModel::userID($mUser->user_id)->status(2)->count()
            ]);
        }

        return $this->responseDataJson(['Bad Request'], false);
    }

    /**
     * Upload cover
     * @param  Request $request  [description]
     * @param  [type]  $username [description]
     * @return [type]            [description]
     */
    public function uploadCover(Request $request, $username)
    {
        $validator = app('validator')->make($request->all(), [
            'photo' => 'mimes:jpeg,bmp,png'
        ]);

        // Kiểm tra thông tin input
        if ($validator->fails()) {
            return $this->responseDataJson($this->getValidationError($validator), false);
        }

        // data form
        $dataForm = [
            'token' => $request->header(env('TOKEN_APP', 'Zipidy-Token')),
        ];
        // lấy user dựa vào thông tin input
        $mUser = UserModel::username($username)
                            ->tokenKey($dataForm['token'])->actived()
                            ->get()->first();
        if ($mUser != null) {
            try {
                $file = $request->file('photo');
                $name = $mUser->user_id . '_cover.' . $file->getClientOriginalExtension();
                $file->move(env('DIR_UPLOAD_USER', 'upload/'), $name);

                $mUser->cover = $name;
                $mUser->save();

                return $this->responseDataJson([
                    'cover' => env('DIR_UPLOAD_USER', 'upload/') . $name
                ]);
            } catch (\Exception $e) {
                // var_dump($e->getMessage());
            }
        }
        return $this->responseDataJson(['Bad Request'], false);
    }

    /**
     * Upload avatar
     * @param  Request $request  [description]
     * @param  [type]  $username [description]
     * @return [type]            [description]
     */
    public function uploadAvatar(Request $request, $username)
    {
        $validator = app('validator')->make($request->all(), [
            'photo' => 'mimes:jpeg,bmp,png'
        ]);

        // Kiểm tra thông tin input
        if ($validator->fails()) {
            return $this->responseDataJson($this->getValidationError($validator), false);
        }

        // data form
        $dataForm = [
            'token' => $request->header(env('TOKEN_APP', 'Zipidy-Token')),
        ];
        // lấy user dựa vào thông tin input
        $mUser = UserModel::username($username)
                            ->tokenKey($dataForm['token'])->actived()
                            ->get()->first();
        if ($mUser != null) {
            try {
                $file = $request->file('photo');
                $name = $mUser->user_id . '_avatar.' . $file->getClientOriginalExtension();
                $file->move(env('DIR_UPLOAD_USER', 'upload/'), $name);

                $mUser->avatar = $name;
                $mUser->save();

                return $this->responseDataJson([
                    'cover' => env('DIR_UPLOAD_USER', 'upload/') . $name
                ]);
            } catch (\Exception $e) {
                // var_dump($e->getMessage());
            }
        }
        return $this->responseDataJson(['Bad Request'], false);
    }

    /**
     * Upload cover
     * @param  Request $request  [description]
     * @param  [type]  $username [description]
     * @return [type]            [description]
     */
    public function beIndirect(Request $request, $username)
    {
        $validator = app('validator')->make($request->all(), [
            'bid'  => 'required|numeric|exists:businesses,business_id'
        ]);

        // Kiểm tra thông tin input
        if ($validator->fails()) {
            return $this->responseDataJson($this->getValidationError($validator), false);
        }

        // data form
        $dataForm = [
            'token' => $request->header(env('TOKEN_APP', 'Zipidy-Token')),
            'bid' => $request->input('bid')
        ];
        // lấy user dựa vào thông tin input
        $mUser = UserModel::username($username)
                            ->tokenKey($dataForm['token'])->actived()
                            ->get()->first();

        $mUserBusiness = UserBusinessesModel::userID($mUser->user_id)
                            ->businessID($dataForm['bid'])->userType([4])->get()->first();

        if ($mUser != null && $mUserBusiness == null) {
            try {
                // Save business user
                UserBusinessesModel::create([
                    'user_id' => $mUser->user_id,
                    'user_type' => 4,
                    'status' => 1,
                    'business_id' => $dataForm['bid']
                ]);

                $re['data']['msg'] = ['OK'];
                return $this->responseDataJson([], true, $re);
            } catch (\Exception $e) {
            }
        }
        return $this->responseDataJson(['Bad Request'], false);
    }

    /**
     * send message to busiess of user paid
     * @param  Request $request  [description]
     * @param  [type]  $username [description]
     * @return [type]            [description]
     */
    public function sendMessageBusiness2(Request $request, $username)
    {
        // Kiểm tra thông tin input
        $validator = app('validator')->make($request->all(), [
            'type' => 'required|numeric',
            'content' => 'required|min:1|max:1000',
            'bid'  => 'required|numeric|exists:businesses,business_id'
        ]);

        if (!$validator->fails()) {
            // data form
            $dataForm = [
                'token' => $request->header(env('TOKEN_APP', 'Zipidy-Token')),
                'type' => $request->input('type'),
                'content' => $request->input('content'),
                'bid' => $request->input('bid')
            ];
            // lấy user dựa vào thông tin input
            $mUser = UserModel::username($username)
                                ->tokenKey($dataForm['token'])->actived()
                                ->get()->first();

            if ($mUser != null) {
                try {
                    app('db')->beginTransaction();

                    // register message
                    $mMessage = MessagesModel::create([
                        'title' => '-',
                        'detail' => $dataForm['content'],
                        'sender_id' => $mUser->user_id,
                        'message_type' => $dataForm['type'],
                        'business_id' => $dataForm['bid'],
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);

                    $file = $request->file('photo');
                    if ($file != null) {
                        $image = $mMessage['message_id'] . '_message.' . $file->getClientOriginalExtension();
                        $file->move(env('DIR_UPLOAD_MESSAGE', 'upload/'), $image);

                        $mMessage->image = $image;
                        $mMessage->save();
                    }

                    $mMessage = MessagesModel::find($mMessage['message_id']);
                    $mMessage->business;

                    $userBelongsBusiness = [];
                    $mBusiness = BusinessModel::find($dataForm['bid']);

                    // Preparing message content
                    $push = new Push();
                    $push->setImage(env('MESSAGE_IMAGE_URI', 'null') . $mMessage->image);
                    $push->setPayload([
                        'team' => 'VNN',
                        'score' => '5.6',
                        'message' => [
                            'title' => $mBusiness->name,
                            'message_id' => $mMessage->message_id,
                            'content' => $mMessage->detail,
                            'reply_id' => 0,
                            'type' => 'message'
                        ]
                    ]);
                    $messageContent = $push->getPush();

                    // send message to direct user | indirect user | owner user
                    foreach ($mBusiness->userBusiness()->get() as $buser) {
                        // set user belong to business, we not send message again
                        $userBelongsBusiness[] = $buser->user_id;
                        $mBUser = UserModel::find($buser->user_id);

                        // check user not is sender | not indirect
                        if ($mUser->user_id != $buser->user_id && $buser->user_type != 4) {
                            MessagePackage::getInstance()->sendMessageToBusiness($mBUser->userSetting, $mMessage, $messageContent);
                        }

                        // check user not is sender | not indirect
                        if ($dataForm['type'] == 1 && $mUser->user_id != $buser->user_id && $buser->user_type == 4) {
                            MessagePackage::getInstance()->sendMessageToBusiness($mBUser->userSetting, $mMessage, $messageContent);
                        }
                    }

                    // only send broacast
                    if ($dataForm['type'] == 1) {
                        $mUserInRange = UserModel::userInRange($mBusiness)->get();
                        // send message to free user
                        foreach ($mUserInRange as $user) {
                            if (!in_array($user->user_id, $userBelongsBusiness)) {
                                MessagePackage::getInstance()->sendMessageToBusiness($user->userSetting, $mMessage, $messageContent);
                            }
                        }
                    }

                    app('db')->commit();
                    return $this->responseDataJson([$mMessage->toArray()]);
                } catch (Exception $e) {
                    app('db')->rollBack();
                    return $this->responseDataJson([$e->getMessage()], false);
                }
            }
        }
        // return $this->responseDataJson($this->getValidationError($validator), false);
        return $this->responseDataJson(['Bad Request'], false);
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

    /**
     * random string for password
     * @param  integer $length [description]
     * @return [type]          [description]
     */
    private function generateRandomString($length = 10)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}
