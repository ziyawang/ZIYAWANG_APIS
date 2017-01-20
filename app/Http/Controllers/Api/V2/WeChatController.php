<?php

/**
 * 临时控制器
 */
namespace App\Http\Controllers\Api\V2;

use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use Dingo\Api\Routing\Helpers;
use JWTAuth;
use App\User;
use App\Project;
use App\Service;
use Cache;
use Tymon\users\Exceptions\JWTException;
use DB;
use PDO;

class WeChatController extends BaseController
{
    function randpw($len=8,$format='ALL'){
        $is_abc = $is_numer = 0;
        $password = $tmp ='';  
        switch($format){
            case 'ALL':
                $chars='ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
                break;
            case 'CHAR':
                $chars='ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
                break;
            case 'NUMBER':
                $chars='0123456789';
                break;
            default :
                $chars='ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
                break;
        }
        mt_srand((double)microtime()*1000000*getmypid());
        while(strlen($password)<$len){
            $tmp =substr($chars,(mt_rand()%strlen($chars)),1);
            if(($is_numer <> 1 && is_numeric($tmp) && $tmp > 0 )|| $format == 'CHAR'){
                $is_numer = 1;
            }
            if(($is_abc <> 1 && preg_match('/[a-zA-Z]/',$tmp)) || $format == 'NUMBER'){
                $is_abc = 1;
            }
            $password.= $tmp;
        }
        if($is_numer <> 1 || $is_abc <> 1 || empty($password) ){
            $password = randpw($len,$format);
        }
        return $password;
    }

    public function done(){
        // $metadata = ['paytype'=>'member','month'=>$member->Month,'payid'=>$payload['payid'],'userid'=>$user->userid];
        $user = $this->auth->user();
        $payload = app('request')->all();
        $member = DB::table('T_CONFIG_MEMBER')->where('MemberID',$payload['payid'])->first();
        $appid = "wx0c117367aa543268";//应用ID
        $mch_id = "1389032102";//商户号
        $nonce_str = $this->randpw($len=32,$format='ALL');//随即字符串32位
        $sign = "";//签名
        $body = "资芽--会员充值";//商品描述
        $out_trade_no = 'KT' . substr(time(),4) . mt_rand(1000,9999);
        $total_fee = $member->Price;;
        $spbill_create_ip = $_SERVER['REMOTE_ADDR'];
        $notify_url = "https://apis.ziyawang.com/wechat/webhooks";
        $trade_type = "APP";
        $key = "WkaIEottaw0wHODpOqyEMFxHrmz4LPjZ";

        $wechatAppPay = new \App\WeChat($appid, $mch_id, $notify_url, $key);
        //1.统一下单方法
        $params['body'] = $body;                       //商品描述
        $params['out_trade_no'] = $out_trade_no;   //自定义的订单号
        $params['total_fee'] = $total_fee;                      //订单金额 只能为整数 单位为分
        $params['trade_type'] = $trade_type;                     //交易类型 JSAPI | NATIVE | APP | WAP 
        $result = $wechatAppPay->unifiedOrder( $params );
        print_r($result); // result中就是返回的各种信息信息，成功的情况下也包含很重要的prepay_id
        //2.创建APP端预支付参数
        /** @var TYPE_NAME $result */
        $data = @$wechatAppPay->getAppPayParams( $result['prepay_id'] );
                    // 根据上行取得的支付参数请求支付即可
        return $data;

    }

    public function webhooks(){
        $payload = app('request')->all();
        file_put_contents('./wechat.txt', serialize($payload));
    }
}
