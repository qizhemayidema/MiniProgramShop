<?php
/**
 * Created by PhpStorm.
 * User: fycy
 * Date: 2018/11/5
 * Time: 9:31
 */

namespace app\api\controller;

use think\Controller;

use app\api\model\User as UserModel;
use app\api\model\UserRanks as UserRanksModel;
use app\api\model\UserCoupon as UserCouponModel;
use app\api\model\Coupon as CouponModel;
use app\api\model\CardOrder as CardOrderModel;
use app\api\model\Card as CardModel;
use think\Exception;

class Base extends Controller
{
    protected $app_id = 'xxx';       //appid
    protected $app_secret = 'xxx'; //小程序秘钥
    protected $mch_id = 'xxx';   //商户号
    protected $pay_key = 'xxxx';        //支付 key

    protected function getOpenIdAndSessionKey($js_code)
    {
        $url = 'https://api.weixin.qq.com/sns/jscode2session?appid='.$this->app_id.'&secret='.$this->app_secret.'&js_code='.$js_code.'&grant_type=authorization_code';

        $res = curl_get_https($url);
        $res = json_decode($res,true);
        if (isset($res['errcode'])) {
            if ($res['errcode'] == 45011) {
                return ['code' => 0, 'data' => '每个用户请求频率限制100分钟一次'];
            } else {
                return ['code' => 0, 'data' => $res];
            }
        }
        return ['code'=>1,'data'=>$res];
    }

    //判断用户等级是否符合逻辑
    protected function checkUserRank($token)        //token值
    {
        $user_model = new UserModel();
        $user_rank_model = new UserRanksModel();
        $user_data = $this->getUserInfo($token,true);
        //如果是普通会员则不予理会
        if ($user_data['rank_id'] == 0){
            return false;
        }
        //如果是消费会员
        $res = $user_rank_model->where('start_score','<=',$user_data['all_score'])->where(['delete_time'=>0])->order('sort_score','desc')->select();
        $temp_rank_id = 0;
        foreach ($res as $key){
            if ($key['id'] == $user_data['rank_id']){
                return false;
            }else{
                $temp_rank_id = $key['id'];
            }
        }
        $user_model->where(['token'=>$token])->update(['rank_id'=>$temp_rank_id]);

    }

    //获取某个sku组合的属性与属性值


    //验证用户某个优惠券是否合法
    protected function checkUserCouponOne($user_id,$coupon_id,$timestamp = 0)   //用户id  优惠券id   时间戳(可选)
    {
        if($timestamp == 0){
            $timestamp = time();
        }
        $user_coupon_model = new UserCouponModel();
        $coupon_model = new CouponModel();
        //验证用户是否拥有此优惠券
        $res = $user_coupon_model->where(['user_id'=>$user_id,'coupon_id'=>$coupon_id,'type'=>1])->find();
        if (!$res){
            return ['code'=>0,'msg'=>'订单所使用的优惠券已被使用'];
//            throw new Exception('订单所使用的优惠券已被使用',1004);
        }
        //验证优惠券是否合法
        $coupon_info = $coupon_model->where(['id'=>$coupon_id])->field('start_time,end_time')->find();
        if ($coupon_info['start_time'] > $timestamp || $coupon_info['end_time'] < $timestamp){
            return ['code'=>0,'msg'=>'订单所使用的优惠券不在使用期限内'];
        }
        return ['code'=>1,'data'=>$coupon_info];
    }

    //消除某个用户的现金卡未支付订单
    protected function checkUserCardOrder($token)       //token值
    {
        $card_order_model = new CardOrderModel();
        $card_model = new CardModel();

        $card_order_model->startTrans();
       try{
           $user_id = $this->getUserInfo($token);
           $card_ids = $card_order_model->where(['is_pay'=>0,'user_id'=>$user_id])->column('card_id');
           $result = [];
           foreach ($card_ids as $key => $value){      //回收库存
               if (isset($result[$value])){
                   $result[$value] += 1;
               }else{
                   $result[$value] = 1;
               }
           }

           foreach ($result as $key => $value){        // key为现金卡id  value 为 回退库存
               $card_model->where(['id'=>$key])->setInc('card_num',$value);
           }

           $card_order_model->where(['is_pay'=>0,'user_id'=>$user_id])->delete();
           $card_order_model->commit();
       }catch (Exception $e){
           $card_order_model->rollback();
           return json(['code'=>0,'data'=>$e->getMessage()]);
       }
    }

    //获取用户id or 信息
    protected function getUserInfo($token,$info = false)
    {
        $user_model = (new UserModel())->where(['token'=>$token]);
        $return = $info ? $user_model->find() :  $user_model->value('id');
        return $return;
    }

    //生成token
    protected function makeToken($timestamp,$openid)
    {
        $salt = 'wanvoeuwbnuo3bgojbwnnhMKNMDLVKNpnvlknvN';       //盐值
        return strtoupper(md5($timestamp .$openid.$salt.mt_rand(100000000,999999999)));
    }

}