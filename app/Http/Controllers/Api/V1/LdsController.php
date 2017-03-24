<?php   
/*
*App版本2方法
*/
namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use Dingo\Api\Routing\Helpers;
use JWTAuth;
use App\User;
use App\Project;
use App\Service;
use App\News;
use App\Video;
use App\Message;
use App\Send;
use Cache;
use Tymon\users\Exceptions\JWTException;
use DB;
class LdsController extends  BaseController{
	//app第二版本中的轮播图接口
	public function  banner(){
		$datas=DB::table("T_P_BANNER")->orderBy("created_at","desc")->take(4)->get();
	       	return $datas;
	}

	//短信通知下载app，从融云获取聊天记录
	public function  sendMessage(){
		$datas=Message::select()->get()->toArray();
		//dd($datas);
		foreach($datas as $data){
			$fromUserId=$data['fromUserId'];
			$targetId=$data['targetId'];
			if($fromUserId==0 || $targetId==0){
				continue;
			}
			$results=Message::select()->where(['fromUserId'=>$targetId,'targetId'=>$fromUserId])->get()->toArray();
			if(!$results){
				$phoneNumbers=DB::table("users")->select("phonenumber")->where("userid",$targetId)->get();
				
				foreach($phoneNumbers as $value){
					$phoneNumber=$value->phonenumber;
				}
				$records=Send::select()->where("receiveId",$targetId)->get()->toArray();
				foreach($records as $record){
					$send_time=$record['updated_at'];
				}
				
				if(!$records){
					$sendResult=$this->_sendMes($phoneNumber);
					if($sendResult){
						Send::insert([
							"receiveId"=>$targetId,
							"receiveNum"=>$phoneNumber,
							 'created_at' =>date("Y-m-d H:i:s", time()),
							 'updated_at'=>date("Y-m-d H:i:s", time())
							]);
					}
				} else {
					$created_at=time()-60*60*24;
					$sendTime=strtotime($send_time);
					$nowCount=Message::where(['fromUserId'=>$fromUserId,'targetId'=>$targetId])->count();
					$lastConut=Message::where(['fromUserId'=>$fromUserId,'targetId'=>$targetId])->where("dateTime","<",$send_time)->count();
					if($created_at>$sendTime && $nowCount!=$lastConut){
						$sendResult=$this->_sendMes($phoneNumber);
						if($sendResult){
							Send::where("receiveNum",$phoneNumber)->update([
								// 'created_at' =>date("Y-m-d H:i:s", time()),
								  'updated_at'=>date("Y-m-d H:i:s", time())
								]);
						}
					}
				}

			}
			
		}
	}


//获取相关视频的信息

	public function  reVideo($id){
		$payload = app('request')->all();
		$startpage = isset($payload['startpage']) ?  $payload['startpage'] : 1;
		$pagecount = isset($payload['pagecount']) ?  $payload['pagecount'] : 5;
		$skipnum = ($startpage-1)*$pagecount;
		$videoId=$id;
		$datas=DB::table("T_V_VIDEOINFO")->select("VideoLabel")->where("VideoID",$videoId)->get();
		foreach($datas as $data){
			$types=$data->VideoLabel;
		}
		$type=explode(",",$types);
		$matchers=array();
		foreach($type as $value){
			if($value=="tj"){
				continue;
			}
			 $results=DB::table("T_V_VIDEOINFO")->skip($skipnum)->take($pagecount)->where("VideoLabel","like","%".$value."%")->where("VideoID","<>",$id)->where("Flag",1)->orderBy("VideoID","desc")->get();
			 $matchers=array_merge($matchers,$results);
		}
	 	$data = [];
        $User = $this->auth->user() ? $this->auth->user()->toArray() : null;
		$Video = new VideoController();
		foreach ($matchers as $matcher) {
			$item = $Video->getInfo($matcher->VideoID,$User);
            $data[] = $item;
		}
		$counts=count($matchers);
		$pages=ceil($counts/$pagecount);

		return $this->response->array(['counts'=>$counts, 'pages'=>$pages, 'data'=>$data, 'currentpage'=>$startpage]);
	}

//app服务方星级认证
	public function star(){
	$payload = app('request')->all();
        	$StarID= $payload['StarID'];
        	$PayName =$payload['PayName'];
        	 $UserID = $this->auth->user()->toArray()['userid'];
        	$ServiceIDs=DB::table("T_U_SERVICEINFO")->select("ServiceID")->where("UserID",$UserID)->get();
        	foreach ($ServiceIDs as $value) {
        		$serviceId=$value->ServiceID;
        	}
        	$ServiceID=$serviceId;
        	$image_path=dirname(base_path()).'/ziyaupload/images/user/';
        	if(!is_dir($image_path)){  
                 mkdir($image_path,0777,true);  
        	}  
        	$imgArray=array();
        	if(!empty($_FILES)){
					foreach($_FILES as  $key=>$file){
					if(isset($_FILES[$key])){
						$baseName=basename($file['name']);
						$extension=strrchr($baseName, ".");
						$newName=time() . mt_rand(1000, 9999).$extension;
				  $target_path = $image_path . $newName;
						 $filePath="/user/".$newName;
					  if(move_uploaded_file($_FILES[$key]["tmp_name"],$target_path)){
							$imgArray[$key]=$filePath;
						}else{
							return $this->response->array(['status_code' => '480','msg'=>"文件上传失败"]);
						}
					}
				}
        	}
		if($imgArray){
        	$Resource=implode(",",$imgArray);
        }else{
        	$Resource="";
        }
        $counts=DB::table("T_U_STAR")->where("StarID",$StarID)->where("UserID",$UserID)->count();
		if($counts){
			$States=DB::table("T_U_STAR")->select("State")->where("StarID",$StarID)->where("UserID",$UserID)->get();
			foreach ($States as $value){
				$count=$value->State;
			}
			if($count!=2){
					$result=DB::table("T_U_STAR")->where("StarID",$StarID)->where("UserID",$UserID)->update([
						"StarID"=>$StarID,
						"PayName"=>$PayName,
						"PayMoney"=>"",
						"UserID"=>$UserID,
						"ServiceID"=>$ServiceID,
						"Channel"=>"",
						"BackNumber"=>"",
						"IP"=>$_SERVER['REMOTE_ADDR'],
						'created_at' =>date("Y-m-d H:i:s", time()),
						"OrderNumber"=> 'KT' . substr(time(),4) . mt_rand(1000,9999),
						"State"=>1,
						"Resource"=>$Resource,
					]);
				}
		}else{
        // 将数据插入数据库
					$result=DB::table("T_U_STAR")->insert([
						"StarID"=>$StarID,
						"PayName"=>$PayName,
						"PayMoney"=>"",
						"UserID"=>$UserID,
						"ServiceID"=>$ServiceID,
						"Channel"=>"",
						"BackNumber"=>"",
						"IP"=>$_SERVER['REMOTE_ADDR'],
						 'created_at' =>date("Y-m-d H:i:s", time()),
						"OrderNumber"=>'KT' . substr(time(),4) . mt_rand(1000,9999),
						"State"=>1,
						"Resource"=>$Resource,
				]);
        	}
 // 创建项目成功
        if ($result) {
            return $this->response->array(['status_code'=>'200','success' => 'Create Pro Success']);
        } else {
            return $this->response->array(['status_code'=>'499','error' => 'Create Pro Error']);
        }    
	}

}