<?php
/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$app->get('/', function () use ($app) {
    return $app->version();
});

$app->get('/mail', 'MailController@mail');

$app->get('/get/countries', 'GetDataController@countries');
$app->get('/get/states', 'GetDataController@states');
$app->get('/get/cities', 'GetDataController@cities');

$app->post('/validation/user/email', 'ValidationController@checkUserEmail');
$app->post('/validation/{username}/email/update', 'ValidationController@checkUserEmailUpdate');
$app->post('/validation/business/email', 'ValidationController@checkBusinessEmail');
$app->post('/validation/business/email/update/{business_id}', 'ValidationController@checkBusinessEmailUpdate');
$app->post('/register', 'UserController@register');
$app->post('/register/business', 'UserController@registerBusiness');
$app->post('/login', 'AuthenticationController@login');
/**
 * forgot password
 */
$app->post('/forgot-password', 'UserController@forgotPassword');

$app->group(['middleware' => 'auth'], function () use ($app) {
    /**
     * share email
     */
    $app->post('/share-email', 'UserController@shareEmail');
    /**
     * get invitation join to other business
     */
    $app->get('/{username}/is-owner', 'UserController@isHaveBusiness');
    /**
     * get invitation join to other business
     */
    $app->get('/{username}/invitation', 'UserController@invitation');
    /**
     * accept invitation join to other business
     */
    $app->post('/{username}/invitation/accept', 'UserController@invitationAccept');
    /**
     * deny invitation join to other business
     */
    $app->post('/{username}/invitation/deny', 'UserController@invitationDeny');
    /**
     * get profile
     */
    $app->get('/{username}/profile', 'UserController@profile');
    /**
     * be indirect
     */
    $app->post('/{username}/be-indirect', 'UserController@beIndirect');
    /**
     * upload user cover
     */
    $app->post('/{username}/upload-cover', 'UserController@uploadCover');
    /**
     * update profile
     */
    $app->post('/{username}/update-profile', 'UserController@updateProfile');
    /**
     * upload user cover
     */
    $app->post('/{username}/upload-avatar', 'UserController@uploadAvatar');
    /**
     * Logout user
     */
    $app->get('/{username}/logout', 'AuthenticationController@logout');

    /**
     * Update location user
     */
    $app->post('/{username}/track-location', 'UserController@trackLatlon');
    /**
     * get data package using for main activity
     */
    $app->get('/{username}/my-data', 'UserController@userDataPackage');
    /**
     * send message
     */
    $app->post('/{username}/send-message', 'UserController@sendMessageBusiness');
    $app->post('/{username}/send-message-business', 'UserController@sendMessageBusiness2');
    /**
     * get list paid user's businesses
     */
    $app->post('/{username}/my-businesses', 'BusinessController@forPaidUser');
    /**
     * get list paid user's businesses
     */
    $app->post('/business/search', 'BusinessController@searchAll');
    /**
     * get business detail
     */
    $app->get('/business/detail/{business_id}', 'BusinessController@detail');
    /**
     * get business detail
     */
    $app->get('/business/{business_id}/users/{user_type}', 'BusinessController@users');
    /**
     * set business's user
     */
    $app->post('/business/{business_id}/add-user', 'BusinessController@addUserToBusiness');
    /**
     * search business's user
     */
    $app->get('/business/{business_id}/search-user', 'BusinessController@searchUser');
    /**
     * unset business's user
     */
    $app->get('/business/{business_id}/unset-user/{user_type}/{email}/{status}', 'BusinessController@changeUserStatus');
    /**
     * upload business cover
     */
    $app->post('business/{business_id}/update-profile', 'BusinessController@updateBusiness');
    /**
     * upload business cover
     */
    $app->post('business/{business_id}/upload-cover', 'BusinessController@uploadCover');
    /**
     * upload business avatar
     */
    $app->post('business/{business_id}/upload-avatar', 'BusinessController@uploadAvatar');
    /**
     * get message detail
     */
    $app->get('/message/detail/{message_id}', 'MessageController@detail');
    /**
     * reply message
     */
    $app->post('/message/reply', 'MessageController@reply');
});
