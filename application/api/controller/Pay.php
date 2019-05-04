<?php

namespace app\api\controller;

use app\api\model\User as UserModel;
use app\api\model\Goods as GoodsModel;
use app\api\model\Cart as CartModel;
use app\api\model\GoodsSku as GoodsSkuModel;
use app\api\model\GoodsSkuUserRanksPrice as SkuRankPriceModel;
use app\api\model\UserRanks as UserRanksModel;
use app\api\model\GoodsUserRanksPrice as UserRankPriceModel;
use app\api\model\UserCoupon as UserCouponModel;
use app\api\model\Coupon as CouponModel;
use app\api\model\UserAddress as UserAddressModel;
use app\api\model\Order as OrderModel;
use app\api\model\OrderGoods as OrderGoodsModel;
use app\api\model\OrderShipping as OrderShippingModel;
use app\api\model\CouponHistory as CouponHistoryModel;
use app\api\model\Card as CardModel;
use app\api\model\CardOrder as CardOrderModel;
use app\api\model\UserCard as UserCardModel;

use think\Exception;
use think\Request;

use think\Validate;

class Pay extends Base
{
    protected $body;            //商品描述
    protected $out_trade_no;    //订单号
    protected $total_fee;       //支付金额
    protected $notify_url;      //异步回调地址
    protected $open_id;         //用户标识
    protected $attach;          //附加数据  此处代表区分交易类型  1 为 商品购买 2 为 现金卡购买

    //商品下单支付接口  此处更改了库存
    /*
     * 返回code 0为错误
     *          1为调起支付
     *          2为账号内余额付款
     * */
    public function shop(Request $request)
    {
        $rule = [
            'token' => 'require',
            'cart_ids' => 'require',
            'address_id' => 'require',
            'coupon_id' => 'require',       //如果没有默认为0
//            'desc' => 'require',
            'post_type' => 'require',       // 方式 1 货到付邮  2  上门自提
        ];

        $message = [
            'token.require' => 'error',
            'address_id.require' => '操作非法3',
            'cart_ids.require' => '操作非法2',
            'coupon_id.require' => '操作非法4',
//            'desc.require' => '操作非法5',
            'post_type.require' => '操作非法6',
        ];

        $this->out_trade_no = date('Ymd') . mt_rand(100000000, 999999999);

        $data = $request->post();

        $user_model = new UserModel();
        $goods_model = new GoodsModel();
        $cart_model = new CartModel();
        $goods_sku_model = new GoodsSkuModel();
        $sku_rank_price_model = new SkuRankPriceModel();
        $user_rank_model = new UserRanksModel();
        $user_rank_price_model = new UserRankPriceModel();
        $user_coupon_model = new UserCouponModel();
        $coupon_model = new CouponModel();
        $address_model = new UserAddressModel();
        $order_model = new OrderModel();
        $order_goods_model = new OrderGoodsModel();
        $order_shipping_model = new OrderShippingModel();

        $validate = new Validate($rule, $message);

        //判断传参是否正确
        if (!$validate->check($data)) {
            return json(['code' => 0, 'data' => $validate->getError()]);
        }
        $data['user_id'] = $this->getUserInfo($data['token']);
        unset($data['token']);
        $data['desc'] = htmlentities($data['desc']);
        //获取用户信息
        $user_info = $user_model->where(['id' => $data['user_id']])->find();
        $this->open_id = $user_info['openid'];
        if (!$this->open_id) {
            return json(['code' => 0, 'data' => '操作非法7']);
        }

        $all_price = 0;         //优惠前 订单总金额
        $coupon_money = 0;      //优惠多少钱
        $coupon_all_price = 0;  //优惠后 订单总金额
        $rank_price_all = 0;    //会员总价格

        //获取购物车信息
        $cart_data = $cart_model->whereIn('id', $data['cart_ids'])->select()->toArray();
        //获取商品ids
        $goods_ids = array_column($cart_data, 'goods_id');

        $goods_ids_count = count($goods_ids);
        $cart_ids_count = count(explode(',', $data['cart_ids']));

        //判断查出商品ids 与 购物车ids 是否一致
        if ($goods_ids_count != $cart_ids_count) {
            return json(['code' => 0, 'data' => '操作错误，请刷新后重试']);
        }

        //根据goods_ids 查询 goods信息
        $goods_data_count = $goods_model->whereIn('id', $goods_ids)->where(['delete_time' => 0])->count();
        //判断查出商品信息条数 与 购物车ids 是否一致
        if ($goods_ids_count != $goods_data_count) {
            return json(['code' => 0, 'data' => '下单商品有变动，请重新下单']);
        }
        //检查用户会员等级
        $this->checkUserRank($data['user_id']);

        //获取用户会员信息
        if ($user_info['rank_id'] != 0) {
            $rank_info = $user_rank_model->where(['id' => $user_info['rank_id']])->find();
        }

        $goods_model->startTrans();

        try {
            //判断优惠券
            if ($data['coupon_id'] != 0) {
                //检查用户拥有的此优惠券是否合法
                $user_coupon_status = $user_coupon_model->where(['user_id' => $data['user_id'], 'coupon_id' => $data['coupon_id']])->value('type');
                if ($user_coupon_status == 2) {
                    throw new Exception('您的优惠券不能重复使用', 1004);
                } elseif ($user_coupon_status == 3) {
                    throw new Exception('您的优惠券已被冻结，无法使用', 1004);
                }

                //检查此优惠券是否可用    是否在使用期间内
                $coupon_info = $coupon_model->where(['id' => $data['coupon_id']])->find();

                if ($coupon_info['start_time'] > time() || $coupon_info['end_time'] < time()) {
                    throw new Exception('您的优惠券不在使用期间');
                }

                //如果优惠券是不能全场可用，则判断是否能用到此订单下的商品
                if ($coupon_info['is_all'] == 0) {
                    $coupon_goods_flag = 0;
                    $coupon_goods_ids = explode(',', $coupon_info['goods_ids']);
                    foreach ($coupon_goods_ids as $key1 => $value1) {
                        foreach ($goods_ids as $key2 => $value2) {
                            if ($value2 == $value1) {
                                $coupon_goods_flag = 1;
                                break 2;
                            }
                        }
                    }
                    if ($coupon_goods_flag == 0) {
                        throw new Exception('您的优惠券不能应用到该订单商品下', 1004);
                    }
                }
            } else {
                $coupon_info = [
                    'name' => '',
                    'id' => 0,
                ];
            }
            $order_goods_data = []; //订单商品表
            foreach ($cart_data as $key => $value) {
                $temp = [];     //订单商品表临时数据
                $goods_info = $goods_model->where(['id' => $value['goods_id']])->field('id,num,price,name,is_attr,goods_attr,goods_attr_val,thumb_img')->find();
                $temp['goods_num'] = $value['goods_num'];
                $temp['goods_name'] = $goods_info['name'];
                $temp['goods_type'] = $goods_info['is_attr'];
                $temp['goods_id'] = $goods_info['id'];
                if ($goods_info['is_attr'] == 1) {        //如果商品开启属性 判断属性值是否被goods表选中

                    $temp['goods_sku_id'] = $value['sku_id'];

                    //获取商品sku信息
                    $sku_info = $goods_sku_model->where(['id' => $value['sku_id']])->where(['delete_time' => 0])->find();
                    if (!$sku_info) {
                        throw new Exception('操作失败，请刷新重试', 1004);
                    }

                    //检查库存
                    if ($sku_info['goods_num'] - $value['goods_num'] < 0) {
                        throw new Exception('商品库存不足，请重新下单', 1004);
                    }

                    //查出会员价格
                    if ($user_info['rank_id'] != 0) {    //如果用户是会员
                        $rank_price = $sku_rank_price_model->alias('sku_rank')
                            ->join('cake_user_ranks rank', 'sku_rank.rank_id = rank.id')
                            ->where(['sku_rank.goods_id' => $value['goods_id']])
                            ->where(['sku_rank.goods_sku_id' => $value['sku_id']])
                            ->where('rank.sort_score', '<=', $rank_info['sort_score'])
                            ->order('rank.sort_score', 'desc')
                            ->value('sku_rank.price');

                        if (!is_numeric($rank_price)) {  //此商品没有会员价格
                            $rank_price_all += $value['goods_num'] * $sku_info['goods_price'];      //会员总价格
                            $temp['goods_rank_price'] = $sku_info['goods_price'];
                        } else {
                            $rank_price_all += $value['goods_num'] * $rank_price;      //会员总价格
                            $temp['goods_rank_price'] = $rank_price;
                        }
                    } else {
                        $temp['goods_rank_price'] = $sku_info['goods_price'];
                    }
                    $all_price += $value['goods_num'] * $sku_info['goods_price'];    //优惠前总价格

                    $temp['goods_price'] = $sku_info['goods_price'];

                    //改变sku库存
                    $goods_sku_model->where(['id' => $value['sku_id']])->setDec('goods_num', $value['goods_num']);

                    //为了订单商品表 获取sku组合json
                    $temp_sku_group = [];
                    $sku_group_data = explode(',', $sku_info['sku_group']);
                    $goods_attr = json_decode($goods_info['goods_attr'], 'true');
                    $goods_attr_val = json_decode($goods_info['goods_attr_val'], 'true');
                    foreach ($goods_attr as $attr_key) {
                        foreach ($goods_attr_val as $attr_val_key) {
                            if ($attr_val_key['attr_id'] == $attr_key['id'] && in_array($attr_val_key['id'], $sku_group_data)) {
                                $temp_sku_group[] = [
                                    'name' => $attr_key['name'],
                                    'value' => $attr_val_key['attr_value'],
                                ];
                            }
                        }
                    }
                    $temp['sku_group'] = json_encode($temp_sku_group, JSON_UNESCAPED_UNICODE);
                } else {                              //如果商品没有开启属性
                    $temp['goods_sku_id'] = 0;
                    //判断库存
                    if ($goods_info['num'] - $value['goods_num'] < 0) {
                        throw new Exception('商品库存不足，请重新下单', 1004);
                    }
                    //检查会员价格
                    if ($user_info['rank_id'] != 0) {
                        $rank_price = $user_rank_price_model->alias('user_rank')
                            ->join('cake_user_ranks rank', 'user_rank.rank_id = rank.id')
                            ->where('rank.sort_score', '<=', $rank_info['sort_score'])
                            ->where(['user_rank.goods_id' => $goods_info['id']])
                            ->order('rank.sort_score', 'desc')
                            ->value('user_rank.price');

                        if (!is_numeric($rank_price)) {  //此商品没有会员价格
                            $rank_price_all += $value['goods_num'] * $goods_info['price'];      //会员总价格
                            $temp['goods_rank_price'] = $goods_info['price'];

                        } else {
                            $rank_price_all += $value['goods_num'] * $rank_price;      //会员总价格
                            $temp['goods_rank_price'] = $rank_price;
                        }
                    } else {
                        $temp['goods_rank_price'] = $goods_info['price'];
                    }
                    $temp['goods_price'] = $goods_info['price'];

                    $all_price += $value['goods_num'] * $goods_info['price'];    //优惠前总价格
                    //改变库存
                    $goods_model->where(['id' => $value['goods_id']])->setDec('num', $value['goods_num']);

                    $temp['sku_group'] = '';

                }
                $temp['goods_thumb_img'] = $goods_info['thumb_img'];
                //订单商品表组合
                $order_goods_data[] = $temp;
            }

            //判断送货地址
            $address_info = $address_model->where(['user_id' => $data['user_id']])->where(['id' => $data['address_id']])->find();
            if (!$address_info) {
                throw new Exception('收货地址错误，请刷新后重新尝试', 1004);
            }
            //计算最终价格
            if ($data['coupon_id'] != 0) {   //如果有优惠券
                if ($rank_price_all != 0) {  //如果是会员     基于会员价计算
                    if ($coupon_info['type'] == 1) {         //满减
                        if ($rank_price_all >= $coupon_info['cond']) {
                            $coupon_all_price = (($all_price * 100) - ($coupon_info['money'] * 100)) / 100;
                            if ($coupon_all_price < 0) {
                                $coupon_all_price = 0;
                            }
                        } else {
                            throw new Exception('选择的优惠券不符合满减条件', 1004);
                        }
                    } elseif ($coupon_info['type'] == 2) {     //折扣
                        $coupon_all_price = number_format($rank_price_all / 10 * $coupon_info['money'],2);
                    } else {
                        throw new Exception('选择的优惠券不符合条件', 1004);
                    }
                } else {      //如果不是会员        //基于原价计算
                    if ($coupon_info['type'] == 1) {         //满减
                        if ($all_price >= $coupon_info['cond']) {
                            $coupon_all_price = (($all_price * 100) - ($coupon_info['money'] * 100)) / 100;
                            if ($coupon_all_price < 0) {
                                $coupon_all_price = 0;
                            }
                        } else {
                            throw new Exception('选择的优惠券不符合满减条件', 1004);
                        }
                    } elseif ($coupon_info['type'] == 2) {     //折扣
                        $coupon_all_price = number_format($all_price / 10 * $coupon_info['money'],2);
                    } else {
                        throw new Exception('选择的优惠券不符合条件', 1004);
                    }
                }
                //计算共优惠多少钱
                $coupon_money = $all_price - $coupon_all_price;
            } else {      //如果没有使用优惠券
                if ($user_info['rank_id'] != 0) {    //如果是会员
                    $coupon_money = $all_price - $rank_price_all;
                    $coupon_all_price = $rank_price_all;
                } else {  //如果不是会员
                    $coupon_all_price = $all_price;
                }
            }
            //订单表组合
            $order_table = [
                'order_code' => $this->out_trade_no,
                'user_id' => $data['user_id'],
                'all_price' => $all_price,
                'coupon_money' => $coupon_money,                 //优惠多少钱
                'coupon_all_price' => $coupon_all_price,
                'status' => 1,
                'user_nick' => $user_info['nick_name'],
                'coupon_name' => $coupon_info['name'],
                'coupon_id' => $coupon_info['id'],
                'post_type' => $data['post_type'],
                'desc' => $data['desc'],
                'create_time' => time(),
            ];
            if ($user_info['rank_id'] != 0) {
                $order_table['user_rank_name'] = $rank_info['rank_name'];
            }

            $order_model->insert($order_table);
            $order_id = $order_model->getLastInsID();
            //订单配送表组合
            $order_shipping_data = [
                'order_id' => $order_id,
                'province' => $address_info['province'],
                'city' => $address_info['city'],
                'region' => $address_info['region'],
                'desc' => $address_info['desc'],
                'user_name' => $address_info['user_name'],
                'user_phone' => $address_info['user_name'],
                'user_telephone' => $address_info['user_telephone'],
            ];
            $order_shipping_model->insert($order_shipping_data);

            //整理 $order_goods_data
            foreach ($order_goods_data as &$order_goods_key) {
                $order_goods_key['order_id'] = $order_id;
            }
            unset($order_goods_key);
            $order_goods_model->insertAll($order_goods_data);

            //删除购物车数据
            $cart_model->whereIn('id', $data['cart_ids'])->delete();

            //如果用户账号上的金额足够购买 则直接扣费
            if ($user_info['money'] >= $coupon_all_price) {
                //账单id order_id

                //如果使用了优惠券
                if ($data['coupon_id'] != 0) {
                    $coupon_history_model = new CouponHistoryModel();
                    //改变优惠券状态
                    $user_coupon_model->where(['user_id' => $data['user_id'], 'coupon_id' => $data['coupon_id']])->update(['type' => 2, 'use_time' => time()]);   //改为已使用
                    //增加优惠券历史记录
                    $coupon_history_model->insert([
                        'coupon_id' => $data['coupon_id'],
                        'user_id' => $data['user_id'],
                        'type' => '2',
                        'time' => time(),
                    ]);
                }

                //改变用户账号余额
                $user_money = $user_info['money'] - $coupon_all_price;
                $user_model->where(['id' => $data['user_id']])->update(['money' => $user_money]);

                //改变order表状态值
//                if ($order_table['post_type'] == 2) {        //如果是自提
//                    $order_model->where(['id' => $order_id])->update(['status' => 3,'pay_time'=>time()]);      //已完成
//                } else {
//                    $order_model->where(['id' => $order_id])->update(['status' => 3,'pay_time'=>time()]);      //已支付
//                }
                $order_model->where(['id' => $order_id])->update(['status' => 3,'pay_time'=>time()]);      //已支付

                foreach ($cart_data as $key12 => $value12) {
                    //改变商品销量
                    $goods_model->where(['id' => $value12['goods_id']])->setInc('all_volume');
                    //如果有sku则改变商品sku销量
                    if ($value12['sku_id'] != 0) {
                        $goods_sku_model->where(['id' => $value12['sku_id']])->setInc('sales_volume');
                    }
                }
                $goods_model->commit();
                return json(['code' => 2, 'data' => 'success']);
            }

            $goods_model->commit();
        } catch (Exception $e) {
            $goods_model->rollback();
            return json(['code' => 0, 'data' => $e->getMessage()]);
        }


        $this->total_fee = abs($coupon_all_price - $user_info['money']) * 100;               //需要支付的金额        优惠后总价 - 用户账号上的金钱
        $this->notify_url = url('api/Pay/notify');
        $this->body = '购买商品';
        $this->attach = 1;

        //拉起支付
        return json(['code' => 1, 'data' => $this->unifiedorder(),'order_code'=>$order_table['order_code']]);

    }

    //订单列表页 支付接口
    /*
     * 进到这里的前提是 账单的真实性已经被验证了
     * 如果有优惠券 需要验证优惠券是否合法
     * 计算账单金额与用户账户上前的金额
     * 拉起支付
     * 返回code 0为错误
     *          1为调起支付
     *          2为账号内余额付款
     * */
    public function orderListPay(Request $request)
    {
        $rule = [
            'order_id'      => 'require',
            'token'       => 'require',
            'order_code'    => 'require',
        ];

        $message = [
            'order_id.require'      => '请求非法',
            'token.require'       => '请求非法',
            'order_code.require'    => '请求非法',
        ];

        $data = $request->post();

        $data['user_id'] = $this->getUserInfo($data['token']);
        $order_model = new OrderModel();
        $order_goods_model = new OrderGoodsModel();
        $order_shipping_model = new OrderShippingModel();
        $goods_sku_model = new GoodsSkuModel();
        $goods_model = new GoodsModel();
        $user_model = new UserModel();
        $user_coupon_model = new UserCouponModel();

        $validate = new Validate($rule,$message);
        if (!$validate->check($data)){
            return json(['code'=>0,'data'=>$validate->getError()]);
        }

        //查询订单是否可查
        $order_info = $order_model->where(['user_id'=>$data['user_id'],'id'=>$data['order_id'],'order_code'=>$data['order_code']])->find();

        if (!$order_info){
            return json(['code'=>0,'data'=>'请求非法1']);
        }

        //查看订单是否是未支付状态
        if ($order_info['status'] != 1){
            return json(['code'=>0,'data'=>'账单已被支付,请稍后进行查看']);
        }

        //用户信息
        $user_info = $user_model->where(['id'=>$order_info['user_id']])->find();

        //订单如果已经超过15天则无法支付 过期
        if (time() > $order_info['create_time'] + 15 * 86400){
            //过期退还商品库存
//            $order_goods_data = $order_goods_model->where(['order_id'=>$order_info['id']])->select();
            $order_goods_model->startTrans();
            try{
                //设置过期标识
                $order_model->where(['id'=>$order_info['id']])->update(['status'=>7]);
                $order_goods_model->commit();
            }catch(Exception $e){
                $order_goods_model->rollback();
                return json(['code'=>0,'data'=>$e->getMessage()]);
            }
            return json(['code'=>0,'data'=>'账单已过期,请重新下单']);

        }

        //验证优惠券合法性
        if ($order_info['coupon_id'] != 0){
            $coupon_res = $this->checkUserCouponOne($user_info['id'],$order_info['coupon_id']);
            if ($coupon_res['code'] == 0){
                $goods_model->startTrans();
                try{
                    $order_model->where(['id'=>$order_info['id']])->delete();
                    $order_goods_model->where(['order_id'=>$order_info['id']])->delete();
                    $order_shipping_model->where(['order_id'=>$order_info['id']])->delete();
                    $goods_model->commit();
                }catch (Exception $e){
                    $goods_model->rollback();
                    return json(['code'=>$e->getMessage()]);
                }
                return json(['code'=>0,'data'=>$coupon_res['msg'].',请重新下单']);
            }
        }

        //计算用户付款金额
        //首先判断用户账号上的金额  这里返回实际微信支付付款金额
        $fukuan = $this->checkUserMoney($user_info['money'],$order_info['coupon_all_price']);
        if ($fukuan == 0){
            $order_model->startTrans();
            try{
                //如果使用了优惠券
                if ($data['coupon_id'] != 0) {
                    $coupon_history_model = new CouponHistoryModel();
                    //改变优惠券状态
                    $user_coupon_model->where(['user_id' => $user_info['id'], 'coupon_id' => $order_info['coupon_id']])->update(['type' => 2, 'pay_time' => time()]);   //改为已使用
                    //增加优惠券历史记录
                    $coupon_history_model->insert([
                        'coupon_id' => $data['coupon_id'],
                        'user_id' => $data['user_id'],
                        'type' => '2',
                        'time' => time(),
                    ]);
                }

                //改变用户账号余额
                $user_money = $user_info['money'] - $order_info['coupon_all_price'];
                $user_model->where(['id' => $user_info['id']])->update(['money' => $user_money]);

                //改变order表状态值
                if ($order_info['post_type'] == 2) {        //如果是自提
                    $order_model->where(['id' => $order_info['id']])->update(['status' => 6,'pay_time'=>time()]);      //已完成
                } else {
                    $order_model->where(['id' => $order_info['id']])->update(['status' => 2,'pay_time'=>time()]);      //已支付
                }
                $order_goods_data =  $order_goods_model->where(['order_id'=>$order_info['id']])->field('goods_id,sku_id')->select();
                foreach ($order_goods_data as $key12 => $value12) {
                    //改变商品销量
                    $goods_model->where(['id' => $value12['goods_id']])->setInc('all_volume');
                    //如果有sku则改变商品sku销量
                    if ($value12['sku_id'] != 0) {
                        $goods_sku_model->where(['id' => $value12['sku_id']])->setInc('sales_volume');
                    }
                }

                $order_model->commit();
                return json(['code'=>2,'data'=>'success']);
            }catch (Exception $e){
                $order_model->rollback();
                return json(['code'=>0,'data'=>$e->getMessage()]);
            }

        }

        $this->body = '购买商品';
        $this->out_trade_no = $order_info['order_code'];
        $this->total_fee = $fukuan * 100;
        $this->notify_url = url('api/Pay/notify');
        $this->open_id = $user_info['openid'];
        $this->attach = 1;

        //拉起支付
        return json(['code' => 1, 'data' => $this->unifiedorder()]);
    }

    //购买现金卡充值账户金额接口
    public function cardPay(Request $request)
    {
        $rule = [
            'card_id'   => 'require',
            'token'   => 'require',
        ];

        $message = [
            'card_id.require'   => '操作非法1',
            'token.require'   => '操作非法2',
        ];

        $data = $request->post();
        $data['user_id'] = $this->getUserInfo($data['token']);

        $card_model = new CardModel();
        $card_order_model = new CardOrderModel();
        $user_model = new UserModel();

        $validate = new Validate($rule,$message);
        if (!$validate->check($data)){
            return json(['code'=>0,'data'=>$validate->getError()]);
        }

        $timestamp = time();        //当前时间戳

        //获得将要购买的现金卡信息
        $card_info = $card_model->field('card_price,card_num,put_time,put_end_time,card_name')->find($data['card_id']);
        //获取用户信息
        $open_id = $user_model->where(['id'=>$data['user_id']])->value('openid');
        //判断 card时间是否合法
        if ($timestamp < $card_info['put_time'] || $timestamp > $card_info['put_end_time']){
            return json(['code'=>0,'data'=>'该现金卡不在购买期限内']);
        }

        //生成数据
        $this->body = '现金卡：'.$card_info['card_name'];
        $this->total_fee = $card_info['card_price'] * 100;
        $this->open_id = $open_id;
        $this->notify_url = url('api/Pay/notify');
        $this->out_trade_no = date('Ymd').'C'. mt_rand(100000000, 999999999);
        $this->attach = 2;

        //订单入库
        $card_order= [
            'order_code'    => $this->out_trade_no,
            'user_id'       => $data['user_id'],
            'card_id'       => $data['card_id'],
            'money'         => $card_info['card_price'],
            'is_pay'        => 0,
            'create_time'   => $timestamp,
        ];
        $card_order_model->startTrans();
        try{
            //修改库存
            if ($card_info['card_num'] <= 0){
                throw new Exception('该现金卡已售罄');
            }
            $card_model->where(['id'=>$data['card_id']])->setDec('card_num');

            $card_order_model->insert($card_order);

            $card_order_model->commit();
        }catch (Exception $e){
            $card_order_model->rollback();
            return json(['code'=>0,'data'=>$e->getMessage()]);

        }
        //拉起支付
        return json(['code'=>1,'data'=>$this->unifiedorder()]);


    }

    /*
     * 微信支付回调
     * attach :
     *          1   => 下单购买的商品
     *          2   => 购买的现金卡
     * */
    public function notify()
    {
        $xml = file_get_contents('php://input');
        $notify_data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        if ($notify_data['result_code'] == 'SUCCESS' && $notify_data['return_code'] == 'SUCCESS'){
            if ($notify_data['attach'] == 1){       //下单购买的商品
                $this->GoodsNotify($notify_data);
            }elseif($notify_data['attach'] == 2){   //购买的现金卡
                $this->cardNotify($notify_data);
            }
        }
    }

    //购买现金卡 微信支付 异步回调接口
    private function cardNotify($notify_data)
    {
        $card_order_model = new CardOrderModel();
        $card_model = new CardModel();
        $user_model = new UserModel();
        $user_card_model = new UserCardModel();

        $card_order_info = $card_order_model->where(['order_code'=>$notify_data['out_trade_no']])->find();      //订单数据
        $card_info = $card_model->where(['id'=>$card_order_info['card_id']])->find();                           //现金卡数据
        $user_info = $user_model->where(['openid'=>$notify_data['openid']])->field('id,all_shop,all_score')->find();                            //用户信息
        //判断订单是否已经支付
        if ($card_order_info['is_pay'] == 1){
            return false;
        }
        $card_order_model->startTrans();
        try{
            //改变订单状态
            $card_order_model->where(['order_code'=>$notify_data['out_trade_no']])->update(['is_pay'=>1,'pay_time'=>time()]);
            //给user_card表增加数据
            $user_card = [
                'user_id'     => $user_info['id'],
                'card_id'     => $card_info['id'],
                'card_code'   => strtoupper(mb_substr(md5(json_encode($notify_data,256).microtime()),0,18)),
                'status'      => 1,
                'from_type'   => 1,
                'is_see'      => 0,
                'card_info'   => json_encode($card_info,256),
                'create_time' => time(),
            ];
            $user_card_model->insert($user_card);
            //改变用户积分及消费总额
            $pay_money = $notify_data['total_fee'] / 100;
            $user_model->where(['id'=>$user_info['id']])->update([
                'all_shop'  => $user_info['all_shop'] + $pay_money,
                'all_score' => $user_info['all_score'] + $pay_money,
            ]);
            $card_order_model->commit();

            //核实用户积分变动与会员信息
            $this->checkUserRank($user_info['id']);
        }catch (Exception $e){
            $card_order_model->rollback();
        }
    }

    //购买商品 微信支付回调
    private function GoodsNotify($notify_data)
    {
        $order_model = new OrderModel();
        $order_goods_model = new OrderGoodsModel();
        $user_model = new UserModel();
        $user_coupon_model = new UserCouponModel();
        $coupon_history_model = new CouponHistoryModel();
        $goods_model = new GoodsModel();
        $goods_sku_model = new GoodsSkuModel();

        $order_code = $notify_data['out_trade_no'];                                      //订单号
        $order_info = $order_model->where(['order_code'=>$order_code])->find();          //订单详情
        $open_id = $notify_data['openid'];                                               //openid
        $user_info = $user_model->where(['openid' =>$open_id])->find();                  //用户信息
        $order_money = $order_info['coupon_all_price'];                                  //支付金额 （包括用户账户余额）
        $timestamp = time();                                                             //支付完成时间
        $order_coupon_id = $order_info['coupon_id'];                                     //订单所使用的优惠券id
        $score = 0;                                                                      //将要增加的积分

        //判断订单是否已经完成支付
        if ($order_info['status'] != 1) {    //如果不是未支付状态
            return false;
        }
        $order_model->startTrans();
        try{
            $user_update = [];
            //首先判断用户账号上的金额  这里返回实际微信支付付款金额
            $fukuan = $this->checkUserMoney($user_info['money'],$order_money);
            if ($fukuan === 0){      //用户不用付款
                $user_update['money'] = $user_info['money'] - $order_money;
            }else{          //用户需要付款 计算积分
                $score += ($order_money - $user_info['money']) * 1;
                $user_update['money'] = 0;
            }

            //用户的消费总额与积分
            $user_update['all_shop'] = $user_info['all_shop'] + $order_money;
            $user_update['all_score'] = $user_info['all_score'] + $score;

            //修改用户信息
            $user_model->where([  'id' => $user_info['id']])->update($user_update);

            //改变订单状态 修改支付时间
            $order_update = [
                'status' => 3,       //待发货,
                'pay_time' => $timestamp,  //支付时间
            ];
            $order_model->where(['order_code' => $order_code])->update($order_update);

            //改变优惠券状态
            if ($order_coupon_id != 0) {     //证明使用了优惠券  改变优惠券使用记录
                $coupon_res = $this->checkUserCouponOne($user_info['id'],$order_coupon_id,$timestamp);
                if ($coupon_res['code'] == 0){
                    throw new Exception($coupon_res['msg'],'1004');
                }
                //改变用户优惠券状态 与 使用时间
                $user_coupon_data = [
                    'type'  => 2,
                    'use_time'  => $timestamp,
                ];
                $user_coupon_model->where(['coupon_id'=>$order_coupon_id,'user_id'=>$user_info['id']])->update($user_coupon_data);

                //新增优惠券使用记录
                $coupon_history_data = [
                    'coupon_id'     => $order_coupon_id,
                    'user_id'       => $user_info['id'],
                    'type'          => 2,
                    'time'          => $timestamp,
                ];
                $coupon_history_model->insert($coupon_history_data);
            }
            $order_goods_data = $order_goods_model->where(['order_id'=>$order_info['id']])->field('id,goods_id,goods_type,goods_num,goods_sku_id')->select();

            //循环改变商品销量
            foreach ($order_goods_data as $goods){
                if ($goods['goods_type'] == 1){ //开启选择属性
                    //改变sku销量
                    $goods_sku_model->where(['id'=>$goods['goods_sku_id']])->setInc('sales_volume',$goods['goods_num']);
                }
                //改变商品销量
                $goods_model->where(['id'=>$goods['goods_id']])->setInc('all_volume',$goods['goods_num']);
            }

            $order_model->commit();

            //核实用户积分变动与会员信息
            $this->checkUserRank($user_info['id']);
        }catch (Exception $e){
            $order_model->rollback();
            file_put_contents('./error.txt',$e->getMessage());
            return false;
        }

    }

    //计算抛去用户余额后的实际微信支付付款金额      返回计算后的实际付款金额
    private function checkUserMoney($user_money,$order_money)       //用户账号余额,账单实际收款金额
    {
        $res = 0;
        if ($user_money <= $order_money) {   //如果订单金额比用户账上大
            $res = $order_money - $user_money;
        }
        return $res;
    }

    //统一下单接口
    private function unifiedorder()
    {
        $url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
        $parameters = array(
            'appid' => $this->app_id,                       //小程序ID
            'mch_id' => $this->mch_id,                      //商户号
            'nonce_str' => $this->createNoncestr(),         //随机字符串
            'body' => $this->body,                          //商品描述
            'out_trade_no' => $this->out_trade_no,           //商户订单号
            'total_fee' => $this->total_fee,                //总金额 单位 分
            'spbill_create_ip' => $_SERVER['REMOTE_ADDR'],  //终端IP
            'notify_url' => $this->notify_url,              //通知地址  确保外网能正常访问
            'openid' => $this->open_id,                     //用户id
            'trade_type' => 'JSAPI',                         //交易类型
            'attach'    => $this->attach,                    //交易类型标识 业务用
        );
        //统一下单签名
        $parameters['sign'] = $this->getSign($parameters);
        $xmlData = $this->arrayToXml($parameters);
        $return = $this->xmlToArray($this->postXmlCurl($xmlData, $url, 60));
        return $return;
    }

    //查询订单接口
    private function orderquery($order_code)
    {
        $url = 'https://api.mch.weixin.qq.com/pay/orderquery';
        $parameters = array(
            'appid' => $this->app_id,                       //小程序ID
            'mch_id' => $this->mch_id,                      //商户号
            'nonce_str' => $this->createNoncestr(),         //随机字符串
            'out_trade_no'  => $order_code,                 //订单号
        );
        //统一下单签名
        $parameters['sign'] = $this->getSign($parameters);
        $xmlData = $this->arrayToXml($parameters);
        $return = $this->xmlToArray($this->postXmlCurl($xmlData, $url, 60));
        return $return;
    }

    //作用：生成签名
    private function getSign($Obj)
    {
        foreach ($Obj as $k => $v) {
            $Parameters[$k] = $v;
        }
        //签名步骤一：按字典序排序参数
        ksort($Parameters);
        $String = $this->formatBizQueryParaMap($Parameters, false);
        //签名步骤二：在string后加入KEY
        $String = $String . "&key=" . $this->pay_key;
        //签名步骤三：MD5加密
        $String = md5($String);
        //签名步骤四：所有字符转为大写
        $result_ = strtoupper($String);
        return $result_;
    }

    private static function postXmlCurl($xml, $url, $second = 30)
    {
        $ch = curl_init();
        //设置超时
        curl_setopt($ch, CURLOPT_TIMEOUT, $second);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE); //严格校验
        //设置header
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        //post提交方式
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);


        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
        curl_setopt($ch, CURLOPT_TIMEOUT, 40);
        set_time_limit(0);


        //运行curl
        $data = curl_exec($ch);
        //返回结果
        if ($data) {
            curl_close($ch);
            return $data;
        } else {
            $error = curl_errno($ch);
            curl_close($ch);
            throw new WxPayException("curl出错，错误码:$error");
        }
    }

    private function arrayToXml($arr)
    {
        $xml = "<root>";
        foreach ($arr as $key => $val) {
            if (is_array($val)) {
                $xml .= "<" . $key . ">" . arrayToXml($val) . "</" . $key . ">";
            } else {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            }
        }
        $xml .= "</root>";
        return $xml;
    }

    //xml转换成数组
    private function xmlToArray($xml)
    {


        //禁止引用外部xml实体


        libxml_disable_entity_loader(true);


        $xmlstring = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);


        $val = json_decode(json_encode($xmlstring), true);


        return $val;
    }

    //作用：格式化参数，签名过程需要使用
    private function formatBizQueryParaMap($paraMap, $urlencode)
    {
        $buff = "";
        ksort($paraMap);
        foreach ($paraMap as $k => $v) {
            if ($urlencode) {
                $v = urlencode($v);
            }
            $buff .= $k . "=" . $v . "&";
        }
        $reqPar;
        if (strlen($buff) > 0) {
            $reqPar = substr($buff, 0, strlen($buff) - 1);
        }
        return $reqPar;
    }

    //作用：产生随机字符串，不长于32位
    private function createNoncestr($length = 32)
    {
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }
}
