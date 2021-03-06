<?php

namespace App\Http\Controllers;

use Cyinf\Repositories\CourseRepository;
use Cyinf\Repositories\NotificationRepository;
use Cyinf\Services\NotificationService;
use Davibennun\LaravelPushNotification\Facades\PushNotification;
use Facebook\Exceptions\FacebookResponseException;
use Facebook\Exceptions\FacebookSDKException;
use Facebook\Facebook;
use Illuminate\Http\Request;
use Auth;
use App\Http\Requests;
use Cyinf\Services\FacebookService;

class FacebookController extends CyinfApiController
{
    /**
     * @var FacebookService
     */
    private $facebookService;

    /**
     * @var CourseRepository
     */
    private $courseRepository;

    /**
     * @var NotificationRepository
     */
    private $notificationRepository;

    private $weekMap = [
        'Monday' => '一',
        'Tuesday' => '二',
        'Wednesday' => '三',
        'Thursday' => '四',
        'Friday' => '五',
        'Saturday' => '六',
        'Sunday' => '日'
    ];

    /**
     * FacebookController constructor.
     * @param FacebookService $facebookService
     * @param CourseRepository $courseRepository
     * @param NotificationRepository $notificationRepository
     */
    public function __construct(FacebookService $facebookService, CourseRepository $courseRepository, NotificationRepository $notificationRepository)
    {
        parent::__construct();
        $this->facebookService = $facebookService;
        $this->courseRepository = $courseRepository;
        $this->notificationRepository = $notificationRepository;
    }


    protected function login( ){
        return $this->facebookService->fbLogin();
    }

    protected function loginCallBack(){
        return $this->facebookService->loginCallBack();
    }

    /*protected function testAccessToken( Request $request ){


        $fb = new Facebook([
            'app_id' => env('FACEBOOK_APP_ID') ,
            'app_secret' => env('FACEBOOK_APP_SECRET'),
            'default_graph_version' => 'v2.5',
        ]);

        $req = $fb->request('POST' , '/' . Auth::user()->FB_conn .  '/notifications' , [
            'href' => 'www.google.com',
            'template' => 'This is a test message',
        ] , env('FACEBOOK_APP_ID') . "|" . env('FACEBOOK_APP_SECRET') );

        // Send the request to Graph
        try {
            $response = $fb->getClient()->sendRequest( $req );
        } catch(FacebookResponseException $e) {
            // When Graph returns an error
            echo 'Graph returned an error: ' . $e->getMessage();

        } catch(FacebookSDKException $e) {
            // When validation fails or other local issues
            echo 'Facebook SDK returned an error: ' . $e->getMessage();
        }

    }*/

    protected function profile( Request $request ){

        $user = \Auth::user();

        if( $user->FB_token === "" ){
            $this->responseCode = 403;
            $this->responseData['error'] = "Not connect Facebook";
            return $this->send_response();
        }

        $fb_name = $this->facebookService->getName( $user->FB_token );
        
        $imageUrl = $user->image_url;
        $this->responseData['data'] = [
            'username' => $fb_name,
            'imageUrl' => $imageUrl
        ];
        
        $this->responseCode = 200;
        
        if( $imageUrl === null)
            $this->responseData['status'] = "unlink";
        else
            $this->responseData['status'] = "success";

        return $this->send_response();
    }
    
    protected function logout(){

        $user = \Auth::user();
        $user->FB_conn = "";
        $user->image_url = "";
        $user->FB_token = "";
        $user->save();

        $this->responseData['status'] = "success";
        $this->responseCode = 200;
        return $this->send_response();
    }

    protected function notify( Request $request ){

        $this->dataRule = [
            'type' => 'between:1,2',
            'course_id' => 'numeric'
        ];

        if( ( $valid = $this->vaild_data_format( $request->all() , ['type' , 'course_id'] ) ) !== true ){
            $this->responseCode = 400;
            $this->responseData['error'] = $valid;
            return $this->send_response();
        }

        $type = $request->get('type');
        $course_id = $request->get('course_id');

        $course = $this->courseRepository->getCourseById( $course_id );
        $user = \Auth::user();
        $now = \Carbon\Carbon::now();

        if( $type === "1" ){
            $title = "Curriculum - 點名通知";
            $content = "點名通知：（" . $this->weekMap[ $now->format("l") ] . "）" . $now->format("H:i") . " " . $user->real_name . " 在" . $course->course_nameCH . "發出了點名通知！" ;
        }
        else {
            $title = "Curriculum - 考試通知";
            $content = "考試通知：（" . $this->weekMap[ $now->format("l") ] . "）" . $now->format("H:i") . " " . $user->real_name . " 在" . $course->course_nameCH . "發出了考試通知！" ;
        }

        try{
            $this->facebookService->sendNotification( $user , $title, $course , $content , $type );
            $this->responseCode = 200;
            $this->responseData['status'] = "success";
        }
        catch( \Exception $e ){
            $this->responseCode = 403;
            $this->responseData['error'] = $e->getMessage();
        }
        finally{
            return $this->send_response();
        }

    }
    

}
