<?php

namespace app\admin\model;

use think\Model;
use app\admin\model\Goods as GoodsModel;

class Coupon extends Model
{
    //获取某分类下商品  优惠券用
    public function getCateGoods($cate_id = 0,$page = 1,$list_row = 20)
    {
        $goods_model = new GoodsModel();
        if ($cate_id != 0) {
            $goods_model = $goods_model->where(['cate_id' => $cate_id]);
        }

        $start_page = $page * $list_row - $list_row;
        $res = $goods_model->where(['delete_time'=>0])->limit($list_row,$start_page)->field('id,name,thumb_img,goods_attr,goods_attr_val,attr_val')->select();
        if ($res){
            foreach ($res as &$key){
                $key['goods_attr'] = json_decode($key['goods_attr'],true);
                $key['goods_attr_val'] = json_decode($key['goods_attr_val'],true);
            }
            unset($key);
            return $res;
        }else{
            return [];
        }
    }
}
