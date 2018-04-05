<?php
namespace App\DataPackage;

use App\Model\Business as BusinessModel;
use App\Model\UserReceiveMessage as UserReceiveMessageModel;
use App\Model\UserBusinesses as UserBusinessesModel;
use App\Model\User as UserModel;
use App\Model\Countries as CountriesModel;
use App\Model\States as StatesModel;
use App\Model\Cities as CitiesModel;

class BusinessPackage extends DataPackage
{
    public static function getInstance()
    {
        if (self::$_instance === null) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * support get bussiness
     * - get business detail
     * - get owner
     * - direct
     * - indirect
     * @param  [type] $businessID [description]
     * @return [type]             [description]
     */
    public function getBusinessById($businessID)
    {
        $mBusiness = BusinessModel::find($businessID);
        if ($mBusiness != null) {
            // get total manager
            $mBusiness->totalManager = UserBusinessesModel::where('business_id', '=', $mBusiness->business_id)
                                ->whereIn('user_type', [1, 2])
                                ->where('status', '=', 1)->count();
            // get total indirect
            $mBusiness->totalIndirect = UserBusinessesModel::where('business_id', '=', $mBusiness->business_id)
                                ->where('user_type', '=', 4)
                                ->where('status', '=', 1)->count();
            // get total direct
            $mBusiness->totalDirect = UserBusinessesModel::where('business_id', '=', $mBusiness->business_id)
                                ->whereIn('user_type', [1, 2, 3])
                                ->where('status', '=', 1)->count();

            // get user belong business
            $owner = null;
            $manager = [];
            $direct = [];
            $indirect = [];
            foreach ($mBusiness->userBusiness()->get() as $ub) {
                $user = UserModel::find($ub->user_id);
                // unset user data
                // unset($user->user_id);
                unset($user->username);
                unset($user->password);
                unset($user->token_key);

                switch ($ub->user_type) {
                    case 1:
                        $owner = $user;
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
            $mBusiness->owner = $owner;
            $mBusiness->manager = $manager;
            $mBusiness->direct = $direct;
            $mBusiness->indirect = $indirect;

            // get country
            $mBusiness->country = CountriesModel::find($mBusiness->country_id);
            // get state
            $mBusiness->state = StatesModel::find($mBusiness->state_id);
        }
        return $mBusiness;
    }
}
