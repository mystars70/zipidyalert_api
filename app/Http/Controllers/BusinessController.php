<?php
namespace App\Http\Controllers;

use Validator;
use Session;
use Hash;
use Illuminate\Http\Request;

use App\Http\Core\BaseController;
use App\Model\Business as BusinessModel;
use App\Model\UserBusinesses as UserBusinessesModel;
use App\Model\UserReceiveMessage as UserReceiveMessageModel;
use App\Model\User as UserModel;
use App\Model\Countries as CountriesModel;
use App\Model\States as StatesModel;
use App\Model\Cities as CitiesModel;

use App\DataPackage\BusinessPackage;
use App\DataPackage\MessagePackage;

use App\FCM\Firebase;
use App\FCM\Push;

use Swift_Mailer;
use Swift_SmtpTransport;
use Swift_Message;

class BusinessController extends BaseController
{
    /**
     * update business
     * @param  Request $request  [description]
     * @return [type]            [description]
     */
    public function updateBusiness(Request $request, $business_id)
    {
        // lấy user dựa vào thông tin input
        $mUser = UserModel::tokenKey($request->header(env('TOKEN_APP', 'Zipidy-Token')))->actived()
                            ->get()->first();

        if ($mUser == null) {
            return $this->responseDataJson(['Bad Request'], false);
        }

        $validator = app('validator')->make($request->all(), [
            'country' => 'required|integer',
            'state' => 'integer',
            'city' => 'required|min:1|max:100',
            'zipcode' => 'integer|min:-2147483648|max:2147483647',
            'bemail' => 'required|max:100|email|unique:businesses,email,' . $business_id . ',business_id',
            'name' => 'min:1|max:45',
            'phone' => 'min:1|max:32'
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
            // get business
            $mBusiness = BusinessModel::find($business_id);

            if ($mBusiness == null) {
                throw new \Exception('Business not found.');
            }

            // data form
            $dataForm = [
                'country' => $request->input('country'),
                'state' => $request->input('state'),
                'city' => $request->input('city'),
                'zipcode' => $request->input('zipcode'),
                'bemail' => $request->input('bemail'),
                'phone' => $request->input('phone'),
                'name' => $request->input('name'),
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

            // Save business
            $mBusiness->name = $dataForm['name'];
            $mBusiness->email = $dataForm['bemail'];
            $mBusiness->country_id = $dataForm['country'];
            $mBusiness->state_id = $dataForm['state'];
            $mBusiness->city_id = $mCity == null ? 0 : $mCity->city_id;
            $mBusiness->city_name = $mCity == null ? $dataForm['city'] : $mCity->city_name;
            $mBusiness->zipcode = $dataForm['zipcode'];
            $mBusiness->save();

            app('db')->commit();

            $re['data']['msg'] = ['OK'];
            return $this->responseDataJson([], true, $re);
        } catch (Exception $e) {
            app('db')->rollBack();
        }

        return $this->responseDataJson(['Bad Request'], false);
    }

    /**
     * Add user to business
     * @param Request $request     [description]
     * @param [type]  $business_id [description]
     */
    public function addUserToBusiness(Request $request, $business_id)
    {
        $validator = app('validator')->make($request->all(), [
            'email.*' => 'required|max:100|email',
            'type' => 'required|integer'
        ]);

        // Kiểm tra thông tin input
        if ($validator->fails()) {
            return $this->responseDataJson(['Bad Request'], false);
        }

        try {
            // get business
            $mBusiness = BusinessModel::find($business_id);

            if ($mBusiness == null) {
                throw new \Exception('Business not found.');
            }

            // data form
            $dataForm = [
                'email' => $request->input('email'),
                'type' => $request->input('type')
            ];

            foreach ($dataForm['email'] as $email) {
                // lấy user dựa vào thông tin input
                $mUser = UserModel::email($email)
                                    ->actived()->get()->first();

                // Create the Transport
                $transport = (new Swift_SmtpTransport('smtp.gmail.com', 465, 'ssl'))
                  ->setUsername(env('EMAIL_USER', ''))
                  ->setPassword(env('EMAIL_PASS', ''));

                // Create the Mailer using your created Transport
                $mailer = new Swift_Mailer($transport);
                if ($mUser == null) {
                    // Create a message
                    $message = new Swift_Message('Zipidy Alert');
                    $message = $message->setFrom(['zipidy.sp@gmail.com' => 'Zipidy Alert']);
                    $message->setTo([$email])
                        ->setBody('Here is the content register user, be user type is ' . $dataForm['type']);
                    // Send the message
                    $result = $mailer->send($message);
                } else {
                    $mUserBusiness = UserBusinessesModel::userID($mUser->user_id)
                                                        ->businessID($business_id)
                                                        ->get()->first();
                    if ($mUserBusiness == null) {
                        // Save business user
                        UserBusinessesModel::create([
                            'user_id' => $mUser->user_id,
                            'user_type' => $dataForm['type'],
                            'status' => 2,
                            'business_id' => $mBusiness->business_id
                        ]);
                        $message = new Swift_Message('Zipidy Alert');
                        $message = $message->setFrom(['zipidy.sp@gmail.com' => 'Zipidy Alert']);
                        $message->setTo([$email])
                            ->setBody('Here is the content active user type is '. $dataForm['type']);
                        // Send the message
                        $result = $mailer->send($message);

                        // send notification
                        $statusName = "";
                        // 1: owner, 2: manager, 3: direct; 4: indirect; 0: free
                        switch ($dataForm['type']) {
                            case 1:
                                $statusName = 'owner';
                                break;
                            case 2:
                                $statusName = 'Manager';
                                break;
                            case 3:
                                $statusName = 'Direct';
                                break;
                            case 4:
                                $statusName = 'Indirect';
                                break;
                            default:
                                $statusName = 'owner';
                                break;
                        }

                        // Preparing message content
                        $push = new Push();
                        $push->setPayload([
                            'team' => 'VNN',
                            'score' => '5.6',
                            'message' => [
                                'title' => $mBusiness->name,
                                'content' => "Sent you an invitation to be " . $statusName,
                                'type' => 'notify',
                                'notify_type' => 0,
                                'business_id' => $mBusiness->business_id
                            ]
                        ]);
                        $messageContent = $push->getPush();
                        // MessagePackage::getInstance()->sendNoticeToDevice($mUser->userSetting, $messageContent);
                    } else {
                        UserBusinessesModel::userID($mUser->user_id)->businessID($business_id)->delete();
                        UserBusinessesModel::create([
                            'user_id' => $mUser->user_id,
                            'user_type' => $dataForm['type'],
                            'status' => $mUserBusiness->status,
                            'business_id' => $mBusiness->business_id
                        ]);
                    }
                }
            }

            $re['data']['msg'] = ['OK'];
            return $this->responseDataJson([], true, $re);
        } catch (Exception $e) {
            return $this->responseDataJson(['Bad Request'], false);
        }
    }

    /**
     * search user in business
     * @param  Request $request     [description]
     * @param  [type]  $business_id [description]
     * @return [type]               [description]
     */
    public function searchUser(Request $request, $business_id)
    {
        $validator = app('validator')->make($request->all(), [
            'search' => 'required',
            'type' => 'required|integer'
        ]);

        // Kiểm tra thông tin input
        if ($validator->fails()) {
            return $this->responseDataJson(['Bad Request'], false);
        }

        $dataForm = [
            'search' => $request->input('search'),
            'type' => $request->input('type')
        ];

        $mUserBusinesses = UserBusinessesModel::businessID($business_id)
                                ->userType([$dataForm['type']])
                                ->select('users_businesses.*')
                                ->join('users', 'users.user_id', '=', 'users_businesses.user_id')
                                ->where(function ($query) use ($dataForm) {
                                    $query->where(app('db')->raw('LOWER(users.email)'), 'like', '%' . mb_strtolower($dataForm['search']) . '%')
                                        ->orWhere(app('db')->raw('LOWER(users.firstname)'), 'like', '%' . mb_strtolower($dataForm['search']) . '%')
                                        ->orWhere(app('db')->raw('LOWER(users.lastname)'), 'like', '%' . mb_strtolower($dataForm['search']) . '%')
                                        ->orWhere(app('db')->raw("LOWER(CONCAT(users.firstname, ' ', users.lastname))"), 'like', '%' . mb_strtolower($dataForm['search']) . '%');
                                })
                                ->get();
        // var_dump($mUserBusinesses); die;
        if ($mUserBusinesses != null) {
            $manager = [];
            foreach ($mUserBusinesses as $ub) {
                $user = UserModel::find($ub->user_id);
                // unset user data
                unset($user->user_id);
                unset($user->username);
                unset($user->password);
                unset($user->token_key);

                $ub->user = $user;
                $manager[] = $ub;
            }

            return $this->responseDataJson($manager);
        }

        return $this->responseDataJson(['Bad Request'], false);
    }

    /**
     * search business
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function searchAll(Request $request)
    {
        $validator = app('validator')->make($request->all(), [
            'search' => 'required',
        ]);

        // Kiểm tra thông tin input
        if ($validator->fails()) {
            return $this->responseDataJson(['Bad Request'], false);
        }

        $dataForm = [
            'token' => $request->header(env('TOKEN_APP', 'Zipidy-Token'))
        ];
        // lấy user dựa vào thông tin input
        $mUser = UserModel::tokenKey($dataForm['token'])->actived()
                            ->get()->first();

        $businessIDIN = [];
        if (($mUserBusinesses = $mUser->userBusinesses) != null) {
            foreach ($mUserBusinesses as &$ub) {
                $mBusiness = $ub->business;
                $businessIDIN[] = $mBusiness->business_id;
            }
        }

        $dataForm = [
            'search' => $request->input('search'),
        ];

        $mBusinesses = BusinessModel::likeName($dataForm['search'])
                                        ->orderBy('name', 'asc')->get();
        foreach ($mBusinesses as &$b) {
            // get country
            $b->country = CountriesModel::find($b->country_id);
            // get state
            $b->state = StatesModel::find($b->state_id);
            // get total indirect
            $b->totalIndirect = UserBusinessesModel::where('business_id', '=', $b->business_id)
                                ->where('user_type', '=', 4)
                                ->where('status', '=', 1)->count();
            // get total direct
            $b->totalDirect = UserBusinessesModel::where('business_id', '=', $b->business_id)
                                ->whereIn('user_type', [1, 2, 3])
                                ->where('status', '=', 1)->count();
        }

        return $this->responseDataJson([
            'businesses' => $mBusinesses,
            'in' => $businessIDIN
        ]);
    }

    /**
     * get all business of paid user
     * @param  Request $request  [description]
     * @param  [type]  $username [description]
     * @return [type]            [description]
     */
    public function forPaidUser(Request $request, $username)
    {
        $dataForm = [
            'token' => $request->header(env('TOKEN_APP', 'Zipidy-Token'))
        ];
        // lấy user dựa vào thông tin input
        $mUser = UserModel::username($username)
                            ->tokenKey($dataForm['token'])->actived()
                            ->get()->first();

        if ($mUser != null) {
            $dataReturn = [];
            if (($mUserBusinesses = $mUser->userBusinesses) != null) {
                foreach ($mUserBusinesses as &$ub) {
                    $mBusiness = $ub->business;
                    // get total indirect
                    $mBusiness->totalIndirect = UserBusinessesModel::where('business_id', '=', $mBusiness->business_id)
                                        ->where('user_type', '=', 4)
                                        ->where('status', '=', 1)->count();
                    // get total direct
                    $mBusiness->totalDirect = UserBusinessesModel::where('business_id', '=', $mBusiness->business_id)
                                        ->whereIn('user_type', [1, 2, 3])
                                        ->where('status', '=', 1)->count();
                    $dataReturn[] = $mBusiness;
                }
            }

            return $this->responseDataJson($dataReturn);
        }

        return $this->responseDataJson(['Bad Request'], false);
    }

    /**
     * get business detail
     * @param  Request $request      [description]
     * @param  [type]  $business_id [description]
     * @return [type]               [description]
     */
    public function detail(Request $request, $business_id)
    {
        $dataForm = [
            'token' => $request->header(env('TOKEN_APP', 'Zipidy-Token'))
        ];

        $mBusiness = BusinessPackage::getInstance()->getBusinessById($business_id);

        // lấy user dựa vào thông tin input
        $mUser = UserModel::tokenKey($dataForm['token'])
                            ->actived()->get()->first();
        if ($mUser  != null && $mBusiness != null) {
            // flag kiểm tra xem có là indirect | direct | manager | owner hay không
            // 1: owner | 2: manager | 3: direct | 4: indirect | 0: free user
            $isUserType = 0;

            if ($mUser->user_id == $mBusiness->owner->user_id) {
                $isUserType = 1;
            }

            if ($isUserType == 0) {
                foreach ($mBusiness->manager as $mn) {
                    if ($mUser->user_id == $mn->user_id) {
                        $isUserType = 2;
                    }
                }
            }

            if ($isUserType == 0) {
                foreach ($mBusiness->direct as $mn) {
                    if ($mUser->user_id == $mn->user_id) {
                        $isUserType = 3;
                    }
                }
            }

            if ($isUserType == 0) {
                foreach ($mBusiness->indirect as $mn) {
                    if ($mUser->user_id == $mn->user_id) {
                        $isUserType = 4;
                    }
                }
            }

            // Check user is free user, indirect, driect, manager
            // get messages belong business
            if ($isUserType == 0 || $isUserType == 4) {
                $mMesssageBusiness = $mBusiness->messages()
                                        ->where('message_type', '=', 1)->orderBy('created_at', 'desc')
                                        ->limit(30)->offset(0)->get();
            } else {
                $mMesssageBusiness = $mBusiness->messages()->orderBy('created_at', 'desc')
                ->limit(30)->offset(0)->get();
            }

            foreach ($mMesssageBusiness as $key_mess => $mess) {
                $user = UserModel::find($mess->sender_id);
                // unset user data
                unset($user->user_id);
                unset($user->username);
                unset($user->password);
                unset($user->token_key);
                $mMesssageBusiness[$key_mess]->sender = $user;
                $mMesssageBusiness[$key_mess]->business = $mBusiness;
            }

            // unset user data
            // unset($mUser->user_id);
            unset($mUser->username);
            unset($mUser->password);
            unset($mUser->token_key);

            return $this->responseDataJson([
                'messages' => $mMesssageBusiness,
                'business' => $mBusiness,
                'info' => $mUser,
                'totalUser' => UserModel::cityID($mBusiness->city_id)->count(),
                'totalBusiness' => BusinessModel::cityID($mBusiness->city_id)->count(),
                'totalNotice' => UserBusinessesModel::userID($mUser->user_id)->status(2)->count(),
                'isUserType' => $isUserType
            ]);
        }

        return $this->responseDataJson(['Bad Request'], false);
    }

    /**
     * get user belong business
     * @param  Request $request      [description]
     * @param  [type]  $business_id [description]
     * @param  [type]  $user_type [description]
     * @return [type]               [description]
     */
    public function users(Request $request, $business_id, $user_type)
    {
        $mBusiness = BusinessModel::find($business_id);

        if ($mBusiness != null) {
            $roles = [1, 2];
            switch ($user_type) {
                case 3:
                    $roles = [1, 2, 3];
                    break;
                case 4:
                    $roles = [4];
                    break;
                default:
                    $roles = [1, 2];
                    break;
            }
            // get total user
            $totalUser = UserBusinessesModel::where('business_id', '=', $mBusiness->business_id)
                                ->whereIn('user_type', $roles)
                                ->where('status', '=', 1)->count();
            $manager = [];
            foreach ($mBusiness->userBusiness()->userType($roles)->get() as $ub) {
                $user = UserModel::find($ub->user_id);
                // unset user data
                unset($user->user_id);
                unset($user->username);
                unset($user->password);
                unset($user->token_key);

                $ub->user = $user;
                $manager[] = $ub;
            }

            return $this->responseDataJson([
                'users' => $manager,
                'totalActived' => $totalUser
            ]);
        }

        return $this->responseDataJson(['Bad Request'], false);
    }

    /**
     * unset business's user
     * @param Request $request     [description]
     * @param [type]  $business_id [description]
     * @param [type]  $user_type   [description]
     * @param [type]  $email       [description]
     */
    public function changeUserStatus(Request $request, $business_id, $user_type, $email, $status)
    {
        $dataForm = [
            'token' => $request->header(env('TOKEN_APP', 'Zipidy-Token'))
        ];

        // lấy user dựa vào thông tin input
        $mUserOwner = UserModel::tokenKey($dataForm['token'])
                            ->actived()->get()->first();

        // get business
        $mBusiness = BusinessModel::find($business_id);

        if ($mUserOwner != null && $mBusiness) {
            $isOwner = $mBusiness->userBusiness()
                        ->userID($mUserOwner->user_id)->userType([1])->get()->first();

            if ($isOwner != null) {
                // get uset is unset
                $mUserUnset = UserModel::email($email)->get()->first();
                $isBelong = $mBusiness->userBusiness()
                            ->userID($mUserUnset->user_id)->userType([$user_type])->get()->first();
                if ($isBelong != null) {
                    $mUserBusiness = $mBusiness->userBusiness()
                                ->userID($mUserUnset->user_id)->userType([$user_type])->delete();

                    UserBusinessesModel::create([
                        'user_id' => $mUserUnset->user_id,
                        'user_type' => $user_type,
                        'status' => $status,
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
     * Upload cover
     * @param  Request $request  [description]
     * @return [type]            [description]
     */
    public function uploadCover(Request $request, $business_id)
    {
        $validator = app('validator')->make($request->all(), [
            'photo' => 'mimes:jpeg,bmp,png'
        ]);

        // Kiểm tra thông tin input
        if ($validator->fails()) {
            return $this->responseDataJson($this->getValidationError($validator), false);
        }

        // get business
        $mBusiness = BusinessModel::find($business_id);

        if ($mBusiness != null) {
            try {
                $file = $request->file('photo');
                $name = $mBusiness->business_id . '_cover.' . $file->getClientOriginalExtension();
                $file->move(env('DIR_UPLOAD_BUSINESS', 'upload/'), $name);

                $mBusiness->cover = $name;
                $mBusiness->save();

                return $this->responseDataJson([
                    'cover' => env('DIR_UPLOAD_BUSINESS', 'upload/') . $name
                ]);
            } catch (\Exception $e) {
                var_dump($e->getMessage());
            }
        }
        return $this->responseDataJson(['Bad Request'], false);
    }

    /**
     * Upload avatar
     * @param  Request $request  [description]
     * @return [type]            [description]
     */
    public function uploadAvatar(Request $request, $business_id)
    {
        $validator = app('validator')->make($request->all(), [
            'photo' => 'mimes:jpeg,bmp,png'
        ]);

        // Kiểm tra thông tin input
        if ($validator->fails()) {
            return $this->responseDataJson($this->getValidationError($validator), false);
        }

        // get business
        $mBusiness = BusinessModel::find($business_id);

        if ($mBusiness != null) {
            try {
                $file = $request->file('photo');
                $name = $mBusiness->business_id . '_avatar.' . $file->getClientOriginalExtension();
                $file->move(env('DIR_UPLOAD_BUSINESS', 'upload/'), $name);

                $mBusiness->avatar = $name;
                $mBusiness->save();

                return $this->responseDataJson([
                    'cover' => env('DIR_UPLOAD_BUSINESS', 'upload/') . $name
                ]);
            } catch (\Exception $e) {
                var_dump($e->getMessage());
            }
        }
        return $this->responseDataJson(['Bad Request'], false);
    }
}
