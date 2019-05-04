<?php

namespace app\api\controller;

use think\Controller;
use think\Exception;
use think\Request;

use think\Validate;

use app\api\model\Order as OrderModel;
use app\api\model\OrderGoods as OrderGoodsModel;
use app\api\model\OrderShipping as OrderShippingModel;

use app\api\model\GoodsSku as GoodsSkuModel;
use app\api\model\Goods as GoodsModel;

class Order extends Base
{

    //用户获取订单列表 根据状态
    public function getOrder(Request $request)
    {
        $rule = [
            'token'       => 'require',
            'status'        => 'require',
        ];

        $message = [
            'token.require'       => 'error',
            'status.require'        => '请携带status',
        ];

        $order_model = new OrderModel();
        $order_goods_model = new OrderGoodsModel();
        $order_shipping_model = new OrderShippingModel();

        $data = $request->post();
        $validate = new Validate($rule,$message);
        if (!$validate->check($data)){
            return json(['code'=>0,'data'=>$validate->getError()]);
        }
        $data['user_id'] = $this->getUserInfo($data['token']);
        unset($data['token']);

        if ($data['status'] == 7){      //自提 正在制作的 商品
            $order_data = $order_model->where(['user_id'=>$data['user_id'],'post_type'=>2,'pick_up_status'=>0])->order('create_time','desc')->select()->toArray();
        }elseif($data['status'] == 8){ //自提 已完成的商品
            $order_data = $order_model->where(['user_id'=>$data['user_id'],'post_type'=>2,'pick_up_status'=>1])->order('create_time','desc')->select()->toArray();
        }else{
            $order_data = $order_model->where(['user_id'=>$data['user_id'],'status'=>$data['status']])->order('create_time','desc')->select()->toArray();
        }
        $res = [];
        foreach ($order_data as $key){
            $temp = [];
            $temp['order_goods'] = $order_goods_model->where(['order_id'=>$key['id']])->select()->toArray();
            $temp['order_shipping'] = $order_shipping_model->where(['order_id'=>$key['id']])->select()->toArray();
            $temp['order_info'] = $key;
            $res[] = $temp;
        }

        return json(['code'=>1,'data'=>$res]);
    }

    //已发货的订单 用户按完成 改变status 为6
    public function setOrderStatusOk(Request $request)
    {
        $rule = [
            'token'         => 'require',
            'order_id'       => 'require',
        ];
        $message = [
            'token.require'     => 'error',
            'order_id.require'  => '请求非法',
        ];

        $order_model = new OrderModel();
        $validate = new Validate($rule,$message);
        $data = $request->param();
        if (!$validate->check($data)){
            return json(['code'=>0,'data'=>$validate->getError()]);
        }
        $user_id = $this->getUserInfo($data['token']);

        $order_model->where(['user_id'=>$user_id,'id'=>$data['order_id']])->update(['status'=>6]);

        return json(['code'=>0,'data'=>'success']);
    }

    //取消订单 前提用户没有付款
    public function setOrderRemove(Request $request)
    {
        $rule = [
            'token'   => 'require',
            'order_id'  => 'require',
        ];

        $message = [
            'token.require'   => 'error',
            'order_id.require'   => '请携带order_id',
        ];

        $order_model = new OrderModel();
        $order_goods_model = new OrderGoodsModel();
        $order_shipping_model = new OrderShippingModel();

        $goods_sku_model = new GoodsSkuModel();
        $goods_model = new GoodsModel();

        $data = $request->post();
        $data['user_id'] = $this->getUserInfo($data['token']);

        $validate = new Validate($rule,$message);

        if (!$validate->check($data)){
            return json(['code'=>0,'data'=>$validate->getError()]);
        }

        //判断是否为未支付状态

        $order_info = $order_model->where(['user_id'=>$data['user_id'],'id'=>$data['order_id'],'status'=>1])->field('id')->find();       //
        if (!$order_info){
            return json(['code'=>0,'data'=>'此订单已支付，无法取消']);
        }

        //取消订单
        $order_model->startTrans();

        try{
            //回滚库存
            $order_goods_data = $order_goods_model->where(['order_id'=>$data['order_id']])->field('goods_id,goods_num,goods_type,goods_sku_id')->select();
            foreach ($order_goods_data as $key){
                if ($key['goods_type'] == 1){   //开启
                    $goods_sku_model->where(['id'=>$key['goods_sku_id']])->setInc('goods_num',$key['goods_num']);
                }else{
                    $goods_model->where(['id'=>$key['goods_id']])->setInc('num',$key['goods_num']);
                }
            }

            $order_model->where(['id'=>$data['order_id']])->delete();
            $order_goods_model->where(['order_id'=>$data['order_id']])->delete();
            $order_shipping_model->where(['order_id'=>$data['order_id']])->delete();


            $order_model->commit();
        }catch (Exception $e){
            $order_model->rollback();
            return json(['code'=>0,'data'=>$e->getMessage()]);
        }

        return json(['code'=>1,'data'=>'success']);

    }

    //根据某个order id 获取订单详情
    public function getOrderInfo(Request $request)
    {
        $order_id = $request->post('order_id');
        $token = $request->post('token');
        $user_id = $this->getUserInfo($token);
        if (!$order_id || !$token || !$user_id){
            return json(['code'=>0,'data'=>'请求非法']);
        }

        $order_model = new OrderModel();
        $order_goods_model = new OrderGoodsModel();
        $order_shipping_model = new OrderShippingModel();

        $order_info = [];

        $order_info['order_data'] = $order_model->where(['user_id'=>$user_id,'id'=>$order_id])->find();
        if (!$order_info['order_data']){
            return json(['code'=>0,'msg'=>'error']);
        }
        $order_info['order_goods'] = $order_goods_model->where(['order_id'=>$order_id])->select();
        $order_info['order_shipping'] = $order_shipping_model->where(['order_id'=>$order_id])->find();

        return json(['code'=>1,'data'=>$order_info]);

    }
}
