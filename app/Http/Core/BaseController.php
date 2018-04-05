<?php
namespace App\Http\Core;

use Laravel\Lumen\Routing\Controller;

class BaseController extends Controller
{

    /**
    * response json
    * @param  array   $data    [description]
    * @param  boolean $error   [description]
    * @param  array   $options [description]
    * @return [type]           [description]
    */
    public function responseDataJson($data = [], $error = true, $options = [])
    {
        try {
            $_response = [
                'success' => true,
                'code' => 100,
                'data' => $data,
            ];

            if (!$error) {
                $_response['success'] = false;
                $_response['code'] = 200;
                $_response['data'] = [];
                $_response['data']['msg'] = $data;
            }

            $_response = array_merge($_response, $options);

            return response()->json($_response);
        } catch (Exception $e) {
            $_response = [
                'success' => false,
                'errors' => [ [ "message" => $e->getMessage(), "code" => $e->getCode() ] ]
            ];
            return response()->json($_response);
        }
    }

    /**
    * Lấy thông tin lỗi khi validation
    * @param  [Validator] $validator Validator
    * @return [Array]            Thông tin bị lỗi
    */
    public function getValidationError($validator)
    {
        $msg = [];
        foreach ($validator->messages()->all() as $error) {
            $msg[] = $error;
        }
        return $msg;
    }
}
