<?php

namespace app\api\controller;

use think\Controller;
use think\Exception;
use think\Request;
use think\Validate;

use app\api\model\Coupon as CouponModel;
use app\api\model\CouponHistory as CouponHistoryModel;
use app\api\model\UserCoupon as UserCouponModel;
use app\api\model\User as UserModel;
use app\api\model\Goods as GoodsModel;

class Coupon extends Base
{
    //获取所有优惠券列表
    public function getCouponAll()
    {
        $coupon_model = new CouponModel();
        $res = $coupon_model->where('put_time','<',time())->where('put_end_time','>',time())->select();
        return json($res);
    }

    //判断新用户领取
    public function checkNewUser(Request $request)
    {
        $rule = [
            'token'   => 'require',
        ];
        $message = [
            'token.require'   => 'error',
        ];
        $data = $request->param();
        $data['user_id'] = $this->getUserInfo($data['token']);
        $user_model = new UserModel();
        $coupon_model = new CouponModel();
        $validate = new Validate($rule,$message);
        if (!$validate->check($data)){
            return json(['code'=>0,'msg'=>$validate->getError()]);
        }
        $user_info = $user_model->find($data['user_id']);
        if ($user_info['all_shop'] == 0){
            $coupon_data = $coupon_model
                ->where('put_time','<',time())
                ->where('put_end_time','>',time())
                ->where(['put_type'=>3])        //新用户领取
                ->select();
            return json(['code'=>1,'data'=>$coupon_data]);
        }
        return json(['code'=>0,'data'=>'无劵可领']);
    }

    //领取优惠券
    public function setUserCoupon(Request $request)
    {
        $rule = [
            'token'         => 'require',
            'coupon_id'     => 'require',
        ];
        $message = [
            'token,require'   => 'error',
            'coupon_id.require' => '请携带coupon_id',
        ];
        $data = $request->post();
        $data['user_id'] = $this->getUserInfo($data['token']);

        $coupon_model = new CouponModel();
        $user_coupon_model = new UserCouponModel();
        $coupon_history_model = new CouponHistoryModel();

        $validate = new Validate($rule,$message);
        if (!$validate->check($data)){
            return json(['code'=>0,'data'=>$validate->getError()]);
        }
        $coupon_model->startTrans();
        try{
            //判断是否存在此优惠券
            $coupon_info = $coupon_model->find($data['coupon_id']);
            if (!$coupon_info){
                throw new Exception('不存在此优惠券',1004);
            }
            //判断用户是否领取过该优惠券
            if ($coupon_history_model->where(['user_id'=>$data['user_id'],'coupon_id'=>$data['coupon_id']])->find()){
                throw new Exception('您已领过，不能再次领取',1004);

            }
            //如果没领取过 优惠券为 put_type 为 2
            if ($coupon_info['put_type'] == 2){
                //判断库存
                if ($coupon_info['count'] == 0){
                    throw new Exception('该优惠券已被领完',1004);
                }
                $coupon_model->where(['id'=>$data['coupon_id']])->setDec('count');     //库存-1
            }

            //领取优惠券 入库到 coupon_history
            $history_data = [
                'coupon_id'     => $data['coupon_id'],
                'user_id'       => $data['user_id'],
                'type'          => 1,       // 1为领取
                'time'   => time(),
            ];

            //领取优惠券 入库到 user_coupon
            $user_coupon_data = [
                'coupon_id'     => $data['coupon_id'],
                'user_id'       => $data['user_id'],
                'type'          => 1,
                'create_time'   => time(),
            ];
            $res = $coupon_history_model->insert($history_data);

            if (!$res){
                throw new Exception('领取错误，请稍后再试',1004);
            }

            $res = $user_coupon_model->insert($user_coupon_data);

            if (!$res){
                throw new Exception('领取错误，请稍后再试',1004);
            }



            $coupon_model->commit();

        }catch (Exception $e){
            $coupon_model->rollback();
            return json(['code'=>0,'data'=>$e->getMessage()]);
        }

        return json(['code'=>1,'data'=>'success']);

    }

    //获取某个用户所有可用优惠券
    public function getCouponUser(Request $request)
    {
        $rule = [
            'token'   => 'require',
        ];
        $message = [
            'token.require'   => 'error',
        ];
        $data = $request->post();
        $data['user_id'] = $this->getUserInfo($data['token']);
        $user_coupon_model = new UserCouponModel();
        $goods_model = new GoodsModel();
        $validate = new Validate($rule,$message);
        if (!$validate->check($data)){
            return json(['code'=>0,'msg'=>$validate->getError()]);
        }
        $coupon_data = $user_coupon_model->alias('user_coupon')
            ->join('coupon coupon','user_coupon.coupon_id = coupon.id')
            ->where(['user_coupon.user_id' => $data['user_id']])
            ->where('coupon.start_time','<',time())
            ->where('coupon.end_time','>',time())
            ->where(['user_coupon.type' => 1])
            ->field('coupon.id,coupon.name,coupon.type,coupon.is_all,coupon.goods_ids,coupon.money,coupon.cond,coupon.start_time,coupon.end_time')
            ->select();

        foreach ($coupon_data as &$key){
            if ($key['is_all'] == 0){
                $key['goods_info'] = $goods_model->whereIn('id',$key['goods_ids'])->field('id,name,thumb_img,is_attr,price,show_price')->select()->toArray();
            }
        }
        unset($key);
        return json(['code'=>1,'data'=>$coupon_data]);
    }

    //获取某个用户所有过期优惠券
    public function getCouponUserTimeOut(Request $request)
    {
        $user_id = $request->post('user_id');
        if (!$user_id){
            return json(['code'=>0,'请求非法']);
        }
        $user_coupon_Model = new UserCouponModel();

        $data =$user_coupon_Model->alias('user_coupon')
            ->join('cake_coupon coupon','user_coupon.coupon_id = coupon.id')
            ->where('end_time','<',time())
            ->field('coupon.*')
            ->select();
        return json(['code'=>1,'data'=>$data]);
    }

    //获取某个用户所有已使用的优惠券
    public function getCouponUserUseOut(Request $request)
    {
        $user_id = $request->post('user_id');
        if (!$user_id){
            return json(['code'=>0,'请求非法']);
        }
        $user_coupon_Model = new UserCouponModel();

        $data =$user_coupon_Model->alias('user_coupon')
                            ->join('cake_coupon coupon','user_coupon.coupon_id = coupon.id')
                            ->where(['user_coupon.type'=> 2])
                            ->field('coupon.*')
                            ->select();

        return json(['code'=>1,'data'=>$data]);
    }

    //用户使用现金券
    public function setUseMoneyCoupon(Request $request)
    {
        $rule = [
            'token'   => 'require',
            'coupon_id' => 'require',
        ];

        $message = [
            'token.require'   => 'error',
            'coupon_id.require' => '请携带coupon_id',
        ];
        $data = $request->post();
        $data['user_id'] = $this->getUserInfo($data['token']);
        $user_coupon_model = new UserCouponModel();
        $coupon_model = new CouponModel();
        $user_model = new UserModel();
        $coupon_history_model = new CouponHistoryModel();
        $validate = new Validate($rule,$message);
        if (!$validate->check($data)){
            return json(['code'=>0,'data'=>$validate->getError()]);
        }

        //判断优惠券是否存在
        $coupon_info = $coupon_model->find($data['coupon_id']);
        if (!$coupon_info){
            return json(['code'=>0,'data'=>'请求非法']);
        }
        if ($coupon_info['type'] != 3){
            return json(['code'=>0,'data'=>'请求非法']);
        }

        //判断用户是否拥有 是否可用
        $user_coupon_info = $user_coupon_model->where([
            'user_id'=>$data['user_id'],
            'coupon_id'=>$data['coupon_id'],
            'type'      => 1,
        ])->find();

        if (!$user_coupon_info){
            return json(['code'=>0,'data'=>'请求非法']);
        }

        //判断此优惠券是否在使用期间
        if ($coupon_info['start_time'] > time() || $coupon_info['end_time'] < time()){
            json(['code'=>0,'data'=>'该现金券不在使用期间内']);
        }

        //执行事务
        $coupon_model->startTrans();
        try{
            //用户账号上加上金额
            $user_model->where(['id'=>$data['user_id']])->inc('all_shop',$coupon_info['money']);

            //改变用户优惠券表的状态
            $user_coupon_model->where(['id'=>$user_coupon_info['id']])->update(['type'=>2,'use_time'=>time()]);        //改成已使用
            //优惠券使用记录
            $coupon_history_model->insert([
                'coupon_id' => $user_coupon_info['id'],
                'user_id'   => $data['user_id'],
                'type'      => 2,
                'time'      => time(),
            ]);
            $coupon_model->commit();
        }catch (Exception $e){
            $coupon_model->rollback();
            return json(['code'=>0,'data'=>$e->getMessage()]);
        }
    }

    //获取用户某种类型可用or 不可用 优惠券
    public function getUserTypeCoupon(Request $request)
    {
        $rule = [
            'token'       => 'require',
            'status'        => 'require',
        ];

        $message = [
            'token.require'       => 'error',
            'status.require'        => '请求请携带status',
        ];
        $data = $request->post();
        $data['user_id'] = $this->getUserInfo($data['token']);
        $user_coupon_model = new UserCouponModel;
        $goods_model = new GoodsModel();
        $validate = new Validate($rule,$message);
        if (!$validate->check($data)){
            return json(['code'=>0,'data'=>$validate->getError()]);
        }

        $user_coupon_model = $user_coupon_model->alias('user_coupon')
                            ->join('cake_coupon coupon','user_coupon.coupon_id = coupon.id')
                            ->where(['user_coupon.user_id' => $data['user_id']]);

        //查询是否是可用的
        if ($data['status'] == 1){  //可用
            $user_coupon_model = $user_coupon_model->where(['user_coupon.type'=>1])->where('start_time','<',time())->where(['end_time','>',time()]);
        }elseif ($data['status'] == 2){ //不可用 (已用)
            $user_coupon_model = $user_coupon_model->where(['user_coupon.type'=>2]);  //已过期
        }else{
            return json(['code'=>0,'data'=>'请求非法']);
        }

        //查询字段
        $res = $user_coupon_model->order('user_coupon.use_time','desc')->limit(100)->field('user_coupon.id user_coupon_id,user_coupon.type status,coupon.*')->select()->toArray();

        foreach ($res as &$key){
            if ($key['is_all'] == 0){
                $key['goods_info'] = $goods_model->whereIn('id',$key['goods_ids'])->field('id,name,thumb_img,is_attr,price,show_price')->select()->toArray();
            }
        }
        unset($key);
        return json(['code'=>1,'data'=>$res]);
    }

    //获取用户在下单页面可使用的优惠券
    public function getUserOrderCoupon(Request $request)
    {
        $rule = [
            'token'       => 'require',
            'money'         => 'require',       //消费总价
            'goods_ids'     => 'require',
        ];

        $message = [
            'token.require'       => 'error',
            'money.require'         => '请携带money',
            'goods_ids.require'     => '请携带goods_ids',
        ];

        $data = $request->post();
        $data['user_id'] = $this->getUserInfo($data['token']);
        $user_coupon_model = new UserCouponModel();
        $validate = new Validate($rule,$message);
        if (!$validate->check($data)){
            return json(['code'=>0,'msg'=>$validate->getError()]);
        }
        unset($data['token']);
        $coupon_data = $user_coupon_model->alias('user_coupon')
            ->join('coupon coupon','user_coupon.coupon_id = coupon.id')
            ->where(['user_coupon.user_id' => $data['user_id']])
            ->where('coupon.start_time','<',time())
            ->where('coupon.end_time','>',time())
            ->where(['user_coupon.type' => 1])
            ->where('coupon.type','<>',3)
            ->field('coupon.id,coupon.name,coupon.type,coupon.is_all,coupon.goods_ids,coupon.money,coupon.cond,coupon.start_time,coupon.end_time')
            ->select();
        $res = [];

        foreach ($coupon_data as $key){
            if ($key['is_all'] == 0){   //如果不是全场可用
                //判断 适用的商品
                $coupon_goods_ids = explode(',',$key['goods_ids']);
                $goods_ids = explode(',',$data['goods_ids']);

                if (!array_intersect($coupon_goods_ids,$goods_ids)){    //如果不适用
                    continue;
                }
            }
            if ($key['type'] == 1){ //如果为满减
                if ($data['money'] < $key['cond']){
                    continue;
                }
            }
            $res[] = $key;
        }

        return json(['code'=>1,'data'=>$res]);
    }

    //获取用户可领优惠券
    public function getUserGetCoupon(Request $request)
    {
        if (!$token = $request->post('token')){
            return json(['code'=>0,'data'=>'error']);
        }
        $user_id = $this->getUserInfo($token);
        $user_coupon_model = new UserCouponModel();
        $coupon_model = new CouponModel();
        //获取用户所有领过的优惠券
        $user_now_coupon_id = $user_coupon_model->where(['user_id'=>$user_id])->column('coupon_id');

        //查询所有不在用户领取过的优惠券数组中的数据
        $data = $coupon_model->whereNotIn('id',$user_now_coupon_id)->where('put_time','<',time())->where('put_end_time','>',time())->where(['delete_time'=>0])->where('count','>',0)->order('id','desc')->select();

        return json(['code'=>1,'data'=>$data]);
    }

}