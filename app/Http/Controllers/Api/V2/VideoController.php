<?php

/**
 * 视频控制器
 */
namespace App\Http\Controllers\Api\V2;

use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use Dingo\Api\Routing\Helpers;
use JWTAuth;
use App\User;
use App\Video;
use Cache;
use Tymon\users\Exceptions\JWTException;
use DB;
use PDO;

class VideoController extends BaseController
{
    public function videoConsume(){
        $payload = app('request')->all();

        //数据库查询信息单价
        $VideoID = $payload['VideoID'];
        $Video = Video::where('VideoID', $VideoID)->first();
        $user = $this->auth->user();
        $UserID = $user->userid;
        $RushTime = date("Y-m-d H:i:s",strtotime('now'));
        //判断是否是收费视频
        $price = $Video->Price;
        if($price == 0){
            return $this->response->array(['status_code' => '200', 'msg' => '非收费视频', 'Account' => $user->Account]);
        }
        //判断是否已经付费
        $tmp = DB::table('T_V_CONSUME')->where('UserID', $UserID)->where('VideoID', $Video->VideoID)->first();
        if($tmp){
            return $this->response->array(['status_code' => '417', 'msg' => '已支付']);
        }
        //用户余额小于价格返回余额不足
        if($user->Account < $price){
            return $this->response->array(['status_code' => '418', 'msg' => '余额不足']);
        }

        DB::table('T_V_CONSUME')->insert(['VideoID'=>$VideoID, 'UserID'=>$UserID, 'Time'=>$RushTime]);

        //整理插入数据
        $data = array();
        $data['UserID'] = $user->userid;
        $data['Type'] = 2;
        $data['Money'] = $price;
        $data['OrderNumber'] = 'XF' . substr(time(),4) . mt_rand(1000,9999);
        $data['Account'] = $user->Account - $price;
        $data['VideoID'] = $VideoID;
        $data['Flag'] = 1;
        $data['created_at'] = date('Y-m-d H:i:s',time());
        $data['IP'] = $_SERVER['REMOTE_ADDR'];
        $data['timestamp'] = time();
        $data['Operates'] = "查看名称为《" . $Video->VideoTitle . "》的视频，消费" . $price . "芽币";


        DB::beginTransaction();
        try {
            DB::table("T_U_MONEY")->insert($data);
            $user->Account = $user->Account - $price;
            $user->save();

            DB::commit();
        } catch (Exception $e){
            DB::rollback();
            throw $e;
        }

        if(!isset($e)){
            return $this->response->array(['status_code' => '200','Account' => $data['Account']]);
        }
    }
}
