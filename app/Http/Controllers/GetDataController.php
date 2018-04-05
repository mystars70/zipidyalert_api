<?php
namespace App\Http\Controllers;

use Validator;
use Session;
use Hash;
use Illuminate\Http\Request;

use App\Http\Core\BaseController;
use App\Model\Countries as CountriesModel;
use App\Model\States as StatesModel;
use App\Model\Cities as CitiesModel;

class GetDataController extends BaseController
{

    /**
     * lấy countries
     * @param  Request $request  [description]
     * @return [type]            [description]
     */
    public function countries(Request $request)
    {
        $mCountries = CountriesModel::all();
        return $this->responseDataJson($mCountries);
    }

    /**
     * lấy states
     * @param  Request $request  [description]
     * @return [type]            [description]
     */
    public function states(Request $request)
    {
        $validator = app('validator')->make($request->all(), [
            'id'  => 'required'
        ]);

        // Kiểm tra thông tin input
        if ($validator->fails()) {
            return $this->responseDataJson(['Bad Request'], false);
        }

        $mStates = StatesModel::countryID($request->input('id'))->get();
        return $this->responseDataJson($mStates);
    }

    /**
     * lấy cities
     * @param  Request $request  [description]
     * @return [type]            [description]
     */
    public function cities(Request $request)
    {
        $validator = app('validator')->make($request->all(), [
            'country'  => 'required',
        ]);

        // Kiểm tra thông tin input
        if ($validator->fails()) {
            return $this->responseDataJson(['Bad Request'], false);
        }

        $mCities = CitiesModel::countryCode($request->input('country'))->stateCode($request->input('state'))->get();
        return $this->responseDataJson($mCities);
    }
}
