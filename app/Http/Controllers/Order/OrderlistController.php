<?php

namespace App\Http\Controllers\Order;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Utils\DBUtil;
use App\Http\Utils\HttpUtil;
use phpDocumentor\Reflection\DocBlock\Tags\Var_;
use App\Http\Utils\UserAccess;

class OrderlistController extends Controller
{

    protected $user = [];
    function __construct(Request $request)
    {
        header('Access-Control-Allow-Origin', '*');
        $this->user = UserAccess::getUserInfo();
    }

    /**
     * 查询订单
     * @param $request
     */
    public function getList(Request $request)
    {
        if(empty($this->user['id']))return HttpUtil::retrun_json(['status'=>401,'msg'=>'请先登录'],200);
        $request->userId=$this->user['id'];
        $order = new \App\Models\OrderModel();
        $res=$order->getUser($request->userId);
        if(empty($res))return HttpUtil::retrun_json(['status'=>203,'msg'=>'此ID不是用户'],200);
        if(!is_array($request->where))return HttpUtil::retrun_json(['status'=>203,'msg'=>'条件必须维数组'],200);
        $info=$order->getOrderlist($request,$res->type);
        if(empty($info))return HttpUtil::retrun_json(['status'=>203,'msg'=>'暂无数据'],200);
        return HttpUtil::retrun_json(['data'=>$info['arr'],'sum_amount'=>$info['sum_amount'],'info'=>$info['info'],'status'=>200,'msg'=>'请求成功'],200);

    }

    /**
     * 删除用户订单（批删，单删）
     * @param $request
     */

    public function delList(Request $request)
    {
        if(empty($this->user['id']))return HttpUtil::retrun_json(['status'=>401,'msg'=>'请先登录'],200);
        if(empty($request->oid) || !is_array($request->oid))return HttpUtil::retrun_json(['status'=>203,'msg'=>'oid为空或oid不为数组'],200);
        $order = new \App\Models\OrderModel();
        $res=$order->orderDelect($request->oid);
        if($res==true){
            return HttpUtil::retrun_json(['status'=>200,'msg'=>'删除成功'],200);
        }else{
            return HttpUtil::retrun_json(['status'=>203,'msg'=>'删除失败'],200);
        }
    }

    /**
     * 查询下单人信息
     * @param Request $request
     * @return HttpUtil
     */

    public function showOrder(Request $request)
    {
        if(empty($this->user['id']))return HttpUtil::retrun_json(['status'=>401,'msg'=>'请先登录'],200);
        $order = new \App\Models\OrderModel();
        if(empty($request->recommend_email))return HttpUtil::retrun_json(['data'=>['channel_code'=>0,'code_id'=>0],'status'=>200,'msg'=>'获取成功'],200);
        $arr=$order->getOrder($request->recommend_email);
        if(empty($arr))return HttpUtil::retrun_json(['status'=>203,'msg'=>'此邮箱用户不是推荐人'],200);
        $res=$order->obj_array1($arr);
        $data=[
            "channel_code"=>$res['channel_code'][0],
            "code_id"=>$res['code_id'][0]
        ];
        return HttpUtil::retrun_json(['data'=>$data,'status'=>200,'msg'=>'请求成功'],200);

    }
    /**
     * 订单添加
     * @param Request $request
     * @return HttpUtil
     */

    public function addOrders(Request $request)
    {
        if(empty($this->user['id']))return HttpUtil::retrun_json(['status'=>401,'msg'=>'请先登录'],200);
        $order = new \App\Models\OrderModel();
        if(empty($request->email))return HttpUtil::retrun_json(['status'=>203,'msg'=>'邮箱不能为空'],200);
        if(empty($request->created_time))return HttpUtil::retrun_json(['status'=>203,'msg'=>'创建订单日期'],200);
        if(empty($request->updated_time))return HttpUtil::retrun_json(['status'=>203,'msg'=>'订单支付完成日期'],200);
        if(empty($request->sno))return HttpUtil::retrun_json(['status'=>203,'msg'=>'订单号不能为空'],200);
        if(empty($request->pay_type))return HttpUtil::retrun_json(['status'=>203,'msg'=>'支付类型不能为空'],200);
        if(empty($request->vip_level))return HttpUtil::retrun_json(['status'=>203,'msg'=>'会员等级不能为空'],200);
        if(empty($request->amount))return HttpUtil::retrun_json(['status'=>203,'msg'=>'充值金额不能为空'],200);
        if(empty($request->vip_effective_time))return HttpUtil::retrun_json(['status'=>203,'msg'=>'有效期不能为空'],200);
        if(empty($request->code_id))return HttpUtil::retrun_json(['status'=>203,'msg'=>'推荐优惠id不能为空'],200);
        $res=$order->addNeworder($request);
        if($res==true){
            return HttpUtil::retrun_json(['status'=>200,'msg'=>'订单添加成功'],200);
        }else{
            return HttpUtil::retrun_json(['status'=>203,'msg'=>'订单添加失败'],200);
        }
    }

    /**
     * 订单下载
     * @param Request $request
     * @return HttpUtil
     */

    public  function downLoad(Request $request)
    {
        if(empty($this->user['id']))return HttpUtil::retrun_json(['status'=>401,'msg'=>'请先登录'],200);
        $order= new \App\Models\OrderModel();
        if(empty($request->oid) || !is_array($request->oid))return HttpUtil::retrun_json(['status'=>203,'msg'=>'oid为空或oid不为数组'],200);
        $info=$order->getDownload($request->oid);
        if(empty($info))return HttpUtil::retrun_json(['status'=>203,'msg'=>'暂无数据'],200);
        $order->orderDownload($info);
        return HttpUtil::retrun_json(['data'=>$info,'status'=>200,'msg'=>'请求成功'],200);
    }
}
