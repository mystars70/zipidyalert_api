<?php
namespace App\Http\Controllers;

use Validator;
use Session;
use Hash;
use Illuminate\Http\Request;

use App\Http\Core\BaseController;
use App\Model\User as UserModel;

class ValidationController extends BaseController
{

    /**
     * check email for user
     * @param  Request $request  [description]
     * @return [type]            [description]
     */
    public function checkUserEmail(Request $request)
    {
        $validator = app('validator')->make($request->all(), [
            'email' => 'required|max:100|email|unique:users',
        ]);

        // Kiểm tra thông tin input
        if ($validator->fails()) {
            return $this->responseDataJson($this->getValidationError($validator), false);
        }
        $re['data']['msg'] = ['Email is Valid'];
        return $this->responseDataJson([], true, $re);
    }

    /**
     * check email for user
     * @param  Request $request  [description]
     * @return [type]            [description]
     */
    public function checkUserEmailUpdate(Request $request, $username)
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
            'email' => 'required|max:100|email|unique:users,email,' . $mUser->user_id . ',user_id',
        ]);

        // Kiểm tra thông tin input
        if ($validator->fails()) {
            return $this->responseDataJson($this->getValidationError($validator), false);
        }
        $re['data']['msg'] = ['Email is Valid'];
        return $this->responseDataJson([], true, $re);
    }

    /**
     * check email for business
     * @param  Request $request  [description]
     * @return [type]            [description]
     */
    public function checkBusinessEmail(Request $request)
    {
        $validator = app('validator')->make($request->all(), [
            'email' => 'required|max:100|email|unique:businesses',
        ]);

        // Kiểm tra thông tin input
        if ($validator->fails()) {
            return $this->responseDataJson($this->getValidationError($validator), false);
        }
        $re['data']['msg'] = ['Email is Valid'];
        return $this->responseDataJson([], true, $re);
    }

    /**
     * check email for business update
     * @param  Request $request  [description]
     * @return [type]            [description]
     */
    public function checkBusinessEmailUpdate(Request $request, $business_id)
    {
        $validator = app('validator')->make($request->all(), [
            'email' => 'required|max:100|email|unique:businesses,email,' . $business_id . ',business_id',
        ]);

        // Kiểm tra thông tin input
        if ($validator->fails()) {
            return $this->responseDataJson($this->getValidationError($validator), false);
        }
        $re['data']['msg'] = ['Email is Valid'];
        return $this->responseDataJson([], true, $re);
    }
}
