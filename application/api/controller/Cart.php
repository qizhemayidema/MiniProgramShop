<?php

namespace app\api\controller;

use think\Exception;
use think\Request;
use think\Validate;

use app\api\model\Cart as CartModel;
use app\api\model\Goods as GoodsModel;
use app\api\model\UserRanks as UserRanksModel;
use app\api\model\GoodsUserRanksPrice as UserRanksPriceModel;
use app\api\model\GoodsSkuUserRanksPrice as SkuRankPriceModel;
use app\api\model\GoodsSku as SkuModel;
use app\api\model\User as UserModel;
use app\api\model\CategoryAttr as AttrModel;
use app\api\model\CategoryAttrVal as AttrValModel;
use app\api\model\UserAddress as UserAddressModel;
use app\api\model\UserCoupon as UserCouponModel;


class Cart extends Base
{
    //单个商品加入购物车
    public function setCart(Request $request)
    {
        $rule = [
            'token'     => 'require',
            'goods_id' => 'require',
            'sku_id' => 'require',
            'goods_num' => 'require'
        ];

        $message = [
            'token.require' => 'error',
            'goods_id.require' => '请求必须携带goods_id',
            'goods_num.require' => '请求必须携带goods_num',
            'sku_id.require' => '请求必须携带sku_id',
        ];

        $data = $request->post();
        $data['user_id'] = $this->getUserInfo($data['token']);
        $cart_model = new CartModel();
        $goods_model = new GoodsModel();
        $sku_model = new SkuModel();
        $validate = new Validate($rule, $message);
        if (!$validate->check($data)) {
            return json(['code' => 0, 'data' => $validate->getError()]);
        }
        unset($data['token']);
        if ($data['sku_id'] == 0) {        //证明没有规格
            if ($goods_model->where(['id' => $data['goods_id']])->value('num') <= 0) {
                return json(['code' => 0, 'data' => '该商品暂时没有库存']);
            }
        } else {          //有规格
            //判断规格库存
            if ($sku_model->where(['id' => $data['sku_id']])->value('goods_num') <= 0) {
                return json(['code' => 0, 'data' => '该商品选中的规格暂时没有库存']);
            }
        }
        //判断购物车表以前有没有
        if ($cart_res = $cart_model->where(['goods_id' => $data['goods_id'], 'user_id' => $data['user_id'], 'sku_id' => $data['sku_id']])->find()) {
            $cart_model->where(['goods_id' => $data['goods_id'], 'user_id' => $data['user_id'], 'sku_id' => $data['sku_id']])->update(['goods_num' => $data['goods_num'] + $cart_res['goods_num']]);
        } else {
            $data['create_time'] = time();
            $cart_model->insert($data);
        }
        return json(['code' => 1, 'data' => 'success']);
    }

    //获取某个用户的购物车    图 价格 规格 名称 购物车数量 id 是否选中
    public function getCart(Request $request)
    {
        $token = $request->post('token');
        if (!$token) {
            return json(['code' => 0, 'data' => 'error']);
        }
        $user_id = $this->getUserInfo($token);
        $cart_model = new CartModel();
        $goods_model = new GoodsModel();
        $user_rank_price_model = new UserRanksPriceModel();
        $sku_rank_price_model = new SkuRankPriceModel();
        $sku_model = new SkuModel();
        $user_model = new UserModel();
        $attr_model = new AttrModel();
        $attr_val_model = new AttrValModel();

        $user_cart = $cart_model->where(['user_id' => $user_id])->select(); // 用户的购物车
        $rank_id = $user_model->where(['id' => $user_id])->value('rank_id');    //用户的会员id

        $data = [];     //最终数据数组
        foreach ($user_cart as $key) {
            $temp = [];
            if ($key['sku_id'] == 0) {  //如果没有规格
                if ($rank_id == 0) {     //不是会员
                    $temp = $goods_model->where(['id' => $key['goods_id']])->field('id goods_id,price now_price,thumb_img,name')->find()->toArray();
                } else {      //是会员
                    $temp = $goods_model->where(['id' => $key['goods_id']])->field('id goods_id,price now_price,thumb_img,name')->find()->toArray();
                    $temp_price = $user_rank_price_model->where(['goods_id' => $key['goods_id'], 'rank_id' => $rank_id])->value('price');
                    $temp['price'] = $temp_price;
                }
                $temp['sku'] = 0;
                $temp['goods_num'] = $key['goods_num'];
            } else {//如果有规格
                if ($rank_id == 0) {     //如果不是会员
                    //查询价格  price : 会员价  now_price 原价
                    $temp['now_price'] = $sku_model->where(['goods_id' => $key['goods_id'], 'id' => $key['sku_id']])->value('goods_price');
                } else {      //如果是会员
                    //查询价格
                    $temp['price'] = $sku_rank_price_model->where([
                        'goods_sku_id' => $key['sku_id'],
                        'rank_id' => $rank_id,
                        'goods_id' => $key['goods_id']
                    ])->value('price');
                    $temp['now_price'] = $sku_model->where(['goods_id' => $key['goods_id'], 'id' => $key['sku_id']])->value('goods_price');
                    if (!$temp['price']){
                        $temp['price'] = $temp['now_price'];
                    }
                }
                //查询 商品图 名称 id
                $goods_info = $goods_model->where(['id' => $key['goods_id']])->field('id,thumb_img,name,goods_attr,goods_attr_val')->find()->toArray();
                $temp['goods_id'] = $goods_info['id'];
                $temp['thumb_img'] = $goods_info['thumb_img'];
                $temp['name'] = $goods_info['name'];
                $temp['goods_num'] = $key['goods_num'];
//                $temp['now_price'] = $temp_price;
                $goods_attr = json_decode($goods_info['goods_attr'], true);
                $goods_attr_val = json_decode($goods_info['goods_attr_val'], true);

                //查询规格的组合数据
                $sku_group = explode(',', $sku_model->where(['id' => $key['sku_id']])->value('sku_group'));
//                //规格名称
                $attr_ids = [];     //规格 ids
//                foreach ($attr_val as $key1){
//                    if (!in_array($key1['attr_id'],$attr_ids)){
//                        $attr_ids[$key1['attr_id']] = $key1['attr_id'];
//                    }
//                }
                //根据 规格ids查询
//                $attr = $attr_model->whereIn('id',$attr_ids)->select()->toArray();
//                foreach ($attr as &$key2){
//                    foreach ($attr_val as $key3){
//                        if ($key3['attr_id'] == $key2['id']){
//                            $key2['val'][] = $key3;
//                        }
//                    }
//                }
                $sku_data = [];
                foreach ($goods_attr as $key2) {
                    foreach ($goods_attr_val as $key3) {
                        if ($key2['id'] == $key3['attr_id'] && in_array($key3['id'], $sku_group)) {
                            $sku_data[] = [
                                'name' => $key2['name'],
                                'value' => $key3['attr_value'],
                            ];
                        }
                    }
                }
                unset($key2);
                $temp['sku'] = $sku_data;
            }
            $temp['is_select'] = $key['is_select'];
            $temp['cart_id'] = $key['id'];
            $data[] = $temp;
        }
        return json(['code' => 1, 'data' => $data]);
    }

    //购物车某个商品+1数量
    public function setCartUp(Request $request)
    {
        $rule = [
            'cart_id' => 'require',
        ];
        $message = [
            'cart_id.require' => '请求请携带cart_id',
        ];
        $data = $request->post();
        $cart_model = new CartModel();
        $goods_model = new GoodsModel();
        $sku_model = new SkuModel();
        $validate = new Validate($rule, $message);
        if (!$validate->check($data)) {
            return json(['code' => 0, 'msg' => $validate->getError()]);
        }

        //判断是否超出库存
        $cart_info = $cart_model->find($data['cart_id']);

        if ($cart_info['sku_id'] == 0) {     //没有规格
            $num = $goods_model->find($cart_info['goods_id'])->value('num');
        } else {      //有规格
            $num = $sku_model->where(['id' => $cart_info['sku_id']])->value('goods_num');
        }

        if ($cart_info['goods_num'] + 1 > $num) {
            return json(['code' => 0, 'data' => '库存不足']);
        }

        $cart_model->where([
            'id' => $data['cart_id'],
        ])->setInc('goods_num');

        return json(['code' => 1, 'data' => 'success']);
    }

    //购物车某个商品-1数量
    public function setCartDown(Request $request)
    {
        $rule = [
            'cart_id' => 'require',
        ];
        $message = [
            'cart_id.require' => '请求请携带cart_id',
        ];
        $data = $request->post();
        $cart_model = new CartModel();
        $validate = new Validate($rule, $message);
        if (!$validate->check($data)) {
            return json(['code' => 0, 'msg' => $validate->getError()]);
        }

        //判断购物车商品的数量 -1
        $cart_info = $cart_model->find($data['cart_id']);


        if ($cart_info['goods_num'] - 1 == 0) {
            return json(['code' => 0, 'data' => '库存不足']);
        }

        $cart_model->where([
            'id' => $data['cart_id'],
        ])->setDec('goods_num');

        return json(['code' => 1, 'data' => 'success']);
    }

    //使购物车里某个商品选中
    public function setCartSelectOn(Request $request)
    {
        $rule = [
            'cart_id' => 'require',
        ];

        $message = [
            'cart_id.require' => '请求请携带cart_id',
        ];
        $data = $request->post();
        $cart_model = new CartModel();
        $validate = new Validate($rule, $message);
        if (!$validate->check($data)) {
            return json(['code' => 0, 'msg' => $validate->getError()]);
        }
        $cart_model->where([
            'id' => $data['cart_id'],
        ])->update(['is_select' => '1']);

        return json(['code' => 1, 'data' => 'success']);
    }

    //使购物车里某个商品取消选中
    public function setCartSelectOff(Request $request)
    {
        $rule = [
            'cart_id' => 'require',
        ];

        $message = [
            'cart_id.require' => '请求请携带cart_id',
        ];
        $data = $request->post();
        $cart_model = new CartModel();
        $validate = new Validate($rule, $message);
        if (!$validate->check($data)) {
            return json(['code' => 0, 'msg' => $validate->getError()]);
        }
        $cart_model->where([
            'id' => $data['cart_id'],
        ])->update(['is_select' => '0']);

        return json(['code' => 1, 'data' => 'success']);
    }

    //购物车某个商品删除掉
    public function setCartDel(Request $request)
    {
        $rule = [
            'cart_id' => 'require',
        ];

        $message = [
            'cart_id.require' => '请求请携带cart_id',
        ];
        $data = $request->post();
        $cart_model = new CartModel();
        $validate = new Validate($rule, $message);
        if (!$validate->check($data)) {
            return json(['code' => 0, 'msg' => $validate->getError()]);
        }
        $cart_model->whereIn('id', $data['cart_id'])->delete();
        return json(['code' => 1, 'data' => 'success']);
    }

    //购物车结算 账单页面
    public function bill(Request $request)
    {
        //验证传参
        $rule = [
            'token' => 'require',
            'cart_ids' => 'require',
        ];

        $message = [];
        $data = $request->post();

        $goods_model = new GoodsModel();
        $cart_model = new CartModel();
        $sku_model = new SkuModel();
        $user_address_model = new UserAddressModel();
        $user_coupon_model = new UserCouponModel();
        $sku_rank_price_model = new SkuRankPriceModel();
        $user_model = new UserModel();
        $user_rank_price_model = new UserRanksPriceModel();
        $user_rank_model = new UserRanksModel();
        $validate = new Validate($rule, $message);
        if (!$validate->check($data)) {
            return json(['code' => 0, 'data' => $validate->getError()]);
        }
        $data['user_id'] = $this->getUserInfo($data['token']);
        unset($token);
        $cart_ids = explode(',', $data['cart_ids']);

        //获取购物车信息
        $cart_data = $cart_model->whereIn('id', $cart_ids)->select()->toArray();

        //检查用户会员是否符合逻辑
        $this->checkUserRank($data['user_id']);

        //获取用户信息
        $user_info = $user_model->where(['id'=>$data['user_id']])->find()->toArray();

        //获取用户等级信息
        if ($user_info['rank_id'] != 0){
            $rank_info = $user_rank_model->where(['id'=>$user_info['rank_id']])->find()->toArray();
        }
        $res = [];
        $goods_model->startTrans();
        try {
            $goods_data = [];
            foreach ($cart_data as $key => $value) {

                //判断ids 是否 都被删除 如被删除 则表明 商品已被后台更改
                if ($value['sku_id'] != 0){
                    if (!$sku_model->where(['delete_time' => 0])->whereIn('id', $value['sku_id'])->count()) {
                        return json(['code' => 0, 'data' => '此商品已被管理员改动，清空购物车再次选择即可']);
                    }
                }

                //验证所选商品是否已被后台删除
                if (!$goods_model->where(['delete_time' => 0])->whereIn('id', $value['goods_id'])->count()) {
                    return json(['code' => 0, 'data' => '此商品已被管理员改动，请重新下单2']);
                }

                $temp = [];
                //获取商品信息
                $goods_info = $goods_model->field('id,name,thumb_img,num,is_attr,price,goods_attr,goods_attr_val')->find($value['goods_id']);
                $goods_attr = json_decode($goods_info['goods_attr'],true);
                $goods_attr_val = json_decode($goods_info['goods_attr_val'],true);
                if (!$goods_info) {
                    return json(['code' => 0, 'data' => '下单出错，请稍后再试']);
                }
                if ($goods_info['is_attr'] == 1) {   //如果商品开启属性选择
                    //获取sku组合信息
                    $sku_info = $sku_model->where(['id' => $value['sku_id']])->find();

                    //判断库存是否合法       库存减去购买数量
                    if ($sku_info['goods_num'] - $value['goods_num'] < 0) {
                        throw new Exception($goods_info['name'] . ' 库存不足');
                    }
                    //获取sku商品的普通价格
                    $goods_info['price'] = $sku_info['goods_price'];

                    //获取该sku组合库存
                    $goods_info['num'] = $sku_info['goods_num'];
                    //获取sku组合商品会员价格
                    if ($user_info['rank_id'] != 0){
                        $rank_price = $sku_rank_price_model->alias('sku_rank')
                            ->join('cake_user_ranks rank','sku_rank.rank_id = rank.id')
                            ->where(['sku_rank.goods_id'=>$value['goods_id']])
                            ->where(['sku_rank.goods_sku_id'=>$value['sku_id']])
                            ->where('rank.sort_score','<=',$rank_info['sort_score'])
                            ->field('rank.rank_name,sku_rank.price rank_price')
                            ->order('rank.sort_score','desc')
                            ->find();
                        $temp['rank_price'] = $rank_price;
                    }else{
                        $temp['rank_price'] = [];
                    }
                    //查询规格
                    $goods_sku = [];
                    foreach ($goods_attr as $key1){
                        foreach ($goods_attr_val as $key2){
                            if ($key1['id'] == $key2['attr_id'] && in_array($key2['id'],explode(',',$sku_info['sku_group']))){
                                $goods_sku[] = [
                                    'name'  => $key1['name'],
                                    'value' => $key2['attr_value'],
                                ];
                            }
                        }
                    }
                    $temp['goods_sku'] = $goods_sku;
                } else {      //如果商品关闭属性选择
                    //判断库存是否合法
                    if ($goods_info['num'] - $value['goods_num'] < 0){
                        throw new Exception($goods_info['name'].' 库存不足',1004);
                    }
                    //获取商品的价格
                    $temp['price'] = $goods_info['price'];
                    if ($user_info['rank_id'] != 0){
                        //获取会员价格
                        $rank_price = $user_rank_price_model->alias('user_rank')
                            ->join('cake_user_ranks rank','user_rank.rank_id = rank.id')
                            ->where('rank.sort_score','<=',$rank_info['sort_score'])
                            ->where(['user_rank.goods_id'=>$goods_info['id']])
                            ->field('rank.rank_name,user_rank.price rank_price')
                            ->order('rank.sort_score','desc')
                            ->find();
                        $temp['rank_price'] = $rank_price;
                    }else{
                        $temp['rank_price'] = [];
                    }
                }
                unset($goods_info['goods_attr']);
                unset($goods_info['goods_attr_val']);
                unset($goods_info['num']);
                $temp['goods_info'] = $goods_info;
                $temp['goods_info']['goods_num'] = $value['goods_num'];
                $goods_data[] = $temp;
            }
            $goods_model->commit();
        } catch (Exception $e) {
            $goods_model->rollback();
            return json(['code' => 0, 'data' => $e->getMessage()]);
        }
        //获取用户收货地址
        $address = $user_address_model->where(['user_id' => $data['user_id']])->where(['is_select' => 1])->find();

        //获取用户的可用优惠券
        $coupon_data = $user_coupon_model->alias('user_coupon')
            ->join('coupon coupon', 'user_coupon.coupon_id = coupon.id')
            ->where(['user_coupon.user_id' => $data['user_id']])
            ->where('coupon.start_time', '<', time())
            ->where('coupon.end_time', '>', time())
            ->where(['user_coupon.type' => 1])
            ->field('coupon.id,coupon.name,coupon.type,coupon.is_all,coupon.goods_ids,coupon.money,coupon.cond')
            ->select();
        //返回数据

        $res['goods_data'] = $goods_data;
        $res['address'] = $address;
        $res['coupon_data'] = $coupon_data;

        return json(['code'=>1,'data'=>$res]);
    }
}