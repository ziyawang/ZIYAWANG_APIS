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

    // public function done(){
    //     // $metadata = ['paytype'=>'member','month'=>$member->Month,'payid'=>$payload['payid'],'userid'=>$user->userid];
    //     $user = $this->auth->user();
    //     $payload = app('request')->all();
    //     $member = DB::table('T_CONFIG_MEMBER')->where('MemberID',$payload['payid'])->first();
    //     $appid = "wx0c117367aa543268";//应用ID
    //     $mch_id = "1389032102";//商户号
    //     $nonce_str = $this->randpw($len=32,$format='ALL');//随即字符串32位
    //     $sign = "";//签名
    //     $body = "资芽--会员充值";//商品描述
    //     $out_trade_no = 'KT' . substr(time(),4) . mt_rand(1000,9999);
    //     $total_fee = $member->Price;
    //     $spbill_create_ip = $_SERVER['REMOTE_ADDR'];
    //     $notify_url = "https://apis.ziyawang.com/wechat/webhooks";
    //     $trade_type = "JSAPI";
    //     $key = "WkaIEottaw0wHODpOqyEMFxHrmz4LPjZ";

    //     $wechatAppPay = new \App\WeChat($appid, $mch_id, $notify_url, $key);
    //     //1.统一下单方法
    //     $params['body'] = $body;                       //商品描述
    //     $params['out_trade_no'] = $out_trade_no;   //自定义的订单号
    //     $params['total_fee'] = $total_fee;                      //订单金额 只能为整数 单位为分
    //     $params['trade_type'] = $trade_type;                     //交易类型 JSAPI | NATIVE | APP | WAP 
    //     $result = $wechatAppPay->unifiedOrder( $params );
    //     print_r($result); // result中就是返回的各种信息信息，成功的情况下也包含很重要的prepay_id
    //     //2.创建APP端预支付参数
    //     /** @var TYPE_NAME $result */
    //     $data = @$wechatAppPay->getAppPayParams( $result['prepay_id'] );
    //                 // 根据上行取得的支付参数请求支付即可
    //     return $data;

    // }

    public function done(){
        // $metadata = ['paytype'=>'member','month'=>$member->Month,'payid'=>$payload['payid'],'userid'=>$user->userid];
        $user = $this->auth->user();
        $payload = app('request')->all();
        if($payload['paytype'] == "member"){
            $member = DB::table('T_CONFIG_MEMBER')->where('MemberID',$payload['payid'])->first();
            $body = "资芽--会员充值";//商品描述
            $out_trade_no = 'KT' . substr(time(),4) . mt_rand(1000,9999);
            $total_fee = $member->Price;
            // $total_fee = 1;//测试数据 上线需要删除
            $notify_url = "https://apis.ziyawang.com/wechat/webhooks/member";            
        } elseif ($payload['paytype'] == "yabi") {
            $yabi = DB::table('T_CONFIG_RATE')->where('RateID',$payload['payid'])->first();
            $body = "资芽--芽币充值";
            $out_trade_no = 'CZ' . substr(time(),4) . mt_rand(1000,9999);
            $total_fee = $yabi->RealMoney;
            $notify_url = "https://apis.ziyawang.com/wechat/webhooks/yabi";
        }
        $appid = "wx0c117367aa543268";//应用ID
        $mch_id = "1389032102";//商户号
        $nonce_str = $this->randpw($len=32,$format='ALL');//随即字符串32位
        $sign = "";//签名
        $spbill_create_ip = $_SERVER['REMOTE_ADDR'];
        $trade_type = "JSAPI";
        $key = "WkaIEottaw0wHODpOqyEMFxHrmz4LPjZ";

        //整理插入数据
        if($payload['paytype'] == 'member'){
            $member = DB::table('T_CONFIG_MEMBER')->where('MemberID',$payload['payid'])->first();
            $data = array();
            // 会员开通记录
            $data['UserID'] = $user->userid;
            $data['Month'] = $member->Month;
            $data['MemberID'] = $payload['payid'];
            $data['OrderNumber'] = $out_trade_no;
            $data['PayMoney'] = $member->Price;
            $data['created_at'] = date('Y-m-d H:i:s',time());
            $data['IP'] = $_SERVER['REMOTE_ADDR'];
            $data['PayFlag'] = 0;
            $data['PayName'] = $payload['payname'];
            DB::table("T_U_MEMBER")->insert($data);
        }
        if($payload['paytype'] == 'yabi'){
            $add = DB::table('T_CONFIG_RATE')->where('RateID',$payload['payid'])->pluck('add');
            $data = array();
            $data['UserID'] = $user->userid;
            $data['Type'] = 1;
            $data['Money'] = intval($yabi->RealMoney)/10 + $add;
            $data['OrderNumber'] = $out_trade_no;
            $data['created_at'] = date('Y-m-d H:i:s',time());
            $data['timestamp'] = time();
            $data['IP'] = $_SERVER['REMOTE_ADDR'];
            $data['RealMoney'] = $yabi->RealMoney;
            $data['Flag'] = 0;
            DB::table("T_U_MONEY")->insert($data);
        }

        $input = new \App\WeChat();//         文档提及的参数规范：商家名称-销售商品类目
        $input->SetBody($body);//         订单号应该是由小程序端传给服务端的，在用户下单时即生成，demo中取值是一个生成的时间戳
        $input->SetOut_trade_no($out_trade_no);//         费用应该是由小程序端传给服务端的，在用户下单时告知服务端应付金额，demo中取值是1，即1分钱
        $input->SetTotal_fee($total_fee);
        $input->SetNotify_url($notify_url);
        $input->SetTrade_type("JSAPI");//         由小程序端传给服务端
        $input->SetOpenid($payload['openid']);//         向微信统一下单，并返回order，它是一个array数组
        $order = \App\WxPayApi::unifiedOrder($input);//         json化返回给小程序端
        header("Content-Type: application/json");
        return $this->response->array(['data'=>$this->getJsApiParameters($order),'ordernumber'=>$out_trade_no]);
    }

     private function getJsApiParameters($UnifiedOrderResult)
    {
        // if(!array_key_exists("appid", $UnifiedOrderResult)
        // || !array_key_exists("prepay_id", $UnifiedOrderResult)
        // || $UnifiedOrderResult['prepay_id'] == "")
        // {
        //     throw new WxPayException("参数错误");
        // }
        $jsapi = new \App\WxPayJsApiPay();
        $jsapi->SetAppid($UnifiedOrderResult["appid"]);
        $timeStamp = time();
        $jsapi->SetTimeStamp("$timeStamp");
        $jsapi->SetNonceStr( \App\WxPayApi::getNonceStr());
        $jsapi->SetPackage("prepay_id=" . $UnifiedOrderResult['prepay_id']);
        $jsapi->SetSignType("MD5");
        $jsapi->SetPaySign($jsapi->MakeSign());
        $parameters = json_encode($jsapi->GetValues());
        return $parameters;
    }

    public function webhooksMember(){
        $payload = app('request')->all();
        $OrderNumber = $payload['ordernumber'];
        $Channel = 'wxxcx';
        $BackNumber = substr($payload['package'],10);
        // 先查有没有同类型会员
        $member = DB::table('T_CONFIG_MEMBER')->where('MemberID',$payload['payid'])->first();
        $MemberName = $member->MemberName;
        $MemberYB = $member->YB;
        $MemberMonth = $member->Month;
        $userid = DB::table('T_U_MEMBER')->where('OrderNumber',$OrderNumber)->pluck('UserID');
        $user = User::where('userid', $userid)->first();
        $tmp = DB::table('T_U_MEMBER')->where(['PayName'=>$MemberName,'UserID'=>$user->userid])->orderBy('EndTime','desc')->first();
        if($tmp){
            //判断,到期就不管，如果没到期
            if(strtotime($tmp->EndTime) > time()){
                $StartTime = $tmp->EndTime;
            } else {
                $StartTime = date('Y-m-d H:i:s',time());
            }
        } else {
            $StartTime = date('Y-m-d H:i:s',time());
        }
        $EndTime = date('Y-m-d H:i:s',strtotime("+$MemberMonth months", strtotime($StartTime)));

        if($MemberYB > 0){
            //赠送芽币
            //整理插入数据
            $data = array();
            $data['UserID'] = $user->userid;
            $data['Type'] = 1;
            $data['Money'] = $member->YB;
            $data['OrderNumber'] = 'ZS' . substr(time(),4) . mt_rand(1000,9999);
            $data['Account'] = $user->Account + $MemberYB;
            $data['Flag'] = 1;
            $data['created_at'] = date('Y-m-d H:i:s',time());
            $data['IP'] = $tmp->IP;
            $data['timestamp'] = time();
            $data['Operates'] = "开通" . $member->Content . "，赠送" . $MemberYB . "个芽币";

            DB::beginTransaction();
            try {
                DB::table("T_U_MONEY")->insert($data);
                $user->Account = $user->Account + $MemberYB;
                $user->save();
                DB::table('T_U_MEMBER')->where('OrderNumber', $OrderNumber)->update(['PayFlag'=>1,'BackNumber'=>$BackNumber, 'Channel'=>$Channel, 'StartTime'=>$StartTime, 'EndTime'=>$EndTime, 'Over'=>0]);

                DB::commit();
            } catch (Exception $e){
                DB::rollback();
                throw $e;
            }
            if(!isset($e)){
                return 'ok';
            }
        }

        DB::table('T_U_MEMBER')->where('OrderNumber', $OrderNumber)->update(['PayFlag'=>1,'BackNumber'=>$BackNumber, 'Channel'=>$Channel, 'StartTime'=>$StartTime, 'EndTime'=>$EndTime]);
        return "ok";
    }

    public function webhooksYabi(){
        $payload = app('request')->all();
        $OrderNumber = $payload['ordernumber'];
        $Channel = 'wxxcx';
        $BackNumber = substr($payload['package'],10);
        $money = DB::table('T_U_MONEY')->where('OrderNumber', $OrderNumber)->first();
        if($money){
            //赠送的钱
            $add = DB::table('T_CONFIG_RATE')->where('RealMoney',$money->RealMoney)->pluck('add');
            $user = User::where('userid', $money->UserID)->first();
            $Account = $user->Account + $money->Money;
            $Operates = '充值' . $money->Money . '芽币';
            if($add > 0 ){
                $Operates = '充值' . ($money->Money-$add) . '芽币，赠送了'.$add.'个芽币';
            }
            $user->Account = $Account;
            DB::beginTransaction();
            try {
                DB::table('T_U_MONEY')->where('OrderNumber', $OrderNumber)->update(['Flag'=>1,'Account'=>$Account, 'BackNumber'=>$BackNumber, 'Channel'=>$Channel, 'Operates'=>$Operates]);
                $user->save();

                DB::commit();
            } catch (Exception $e){
                DB::rollback();
                throw $e;
            }
        }
        if(!isset($e)){
            return 'ok';
        }
    }
}
