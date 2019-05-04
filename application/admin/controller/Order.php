<?php
/**
 * Created by PhpStorm.
 * User: fycy
 * Date: 2018/11/14
 * Time: 13:13
 */

namespace app\admin\controller;

use app\admin\model\Order as OrderModel;
use app\admin\model\OrderGoods as OrderGoodsModel;
use app\admin\model\OrderShipping as OrderShippingModel;
use think\Request;

class Order extends Base
{
    //所有订单列表
    public function index()
    {
        $order_model = new OrderModel();
        $order_data = $order_model->field('id,desc,order_code,user_nick,user_rank_name,post_type,pick_up_status,all_price,coupon_money,coupon_all_price,status,create_time')->order('id','desc')->paginate(20);
        $this->assign('order_data',$order_data);
        return $this->fetch();
    }

    //已完成订单列表
    public function statusOkList()
    {
        $order_model = new OrderModel();
        $order_data = $order_model->where(['status'=>6,'post_type'=>1])->field('id,desc,order_code,user_nick,user_rank_name,post_type,pick_up_status,all_price,coupon_money,coupon_all_price,status,create_time')->order('id','desc')->paginate(20);
        $this->assign('order_data',$order_data);
        return $this->fetch('status_ok');
    }

    //待发货订单列表
    public function statusPendingList()
    {
        $order_model = new OrderModel();
        $order_data = $order_model->where(['status'=>3,'post_type'=>1])->field('id,desc,order_code,user_nick,user_rank_name,post_type,pick_up_status,all_price,coupon_money,coupon_all_price,status,create_time')->order('id','desc')->paginate(20);
        $this->assign('order_data',$order_data);
        return $this->fetch();
    }

    //待发货订单 点击后发货
    public function statusChange(Request $request)
    {
        $order_id = $request->param('id');

        $order_model = new OrderModel();
        $order_model->where(['id'=>$order_id])->update(['status'=>4]);

        return json(['code'=>1,'msg'=>$order_id]);

    }

    //上门自提已完成列表
    public function statusHomeOkList()
    {
        $order_model = new OrderModel();
        $order_data = $order_model->where(['pick_up_status'=>1,'post_type'=>2,'status'=>6])->field('id,desc,order_code,user_nick,user_rank_name,post_type,pick_up_status,all_price,coupon_money,coupon_all_price,status,create_time')->order('id','desc')->paginate(20);
        $this->assign('order_data',$order_data);
        return $this->fetch();

    }

    //上门自提未完成列表
    public function statusHomePaddingList()
    {
        $order_model = new OrderModel();
        $order_data = $order_model->where(['pick_up_status'=>0,'post_type'=>2,'status'=>3])->field('id,desc,order_code,user_nick,user_rank_name,post_type,pick_up_status,all_price,coupon_money,coupon_all_price,status,create_time')->order('id','desc')->paginate(20);
        $this->assign('order_data',$order_data);
        return $this->fetch();
    }

    //上门自提 状态改为已处理
    public function statusHomeChange(Request $request)
    {
        $order_id = $request->param('id');
        $order_model = new OrderModel();
        $order_model->where(['id'=>$order_id])->update(['pick_up_status'=>1,'status'=>6]);

        return json(['code'=>1,'msg'=>'success']);

    }

    //订单详细信息页面
    public function orderInfo(Request $request)
    {
        $order_model = new OrderModel();
        $order_goods_model = new OrderGoodsModel();
        $order_shipping_model = new OrderShippingModel();
        $order_id = $request->param('order_id');

        $order_info = $order_model->where(['id'=>$order_id])->find();
        $order_goods = $order_goods_model->where(['order_id'=>$order_id])->select();
        $order_shipping = $order_shipping_model->where(['order_id'=>$order_id])->find();

        $this->assign('order_info',$order_info);
        $this->assign('order_goods',$order_goods);
        $this->assign('order_shipping',$order_shipping);

        return $this->fetch();
    }


}