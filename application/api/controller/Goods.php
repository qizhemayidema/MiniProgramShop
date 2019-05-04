<?php
/**
 * Created by PhpStorm.
 * User: fycy
 * Date: 2018/11/2
 * Time: 10:16
 */

namespace app\api\controller;

use app\api\model\Goods as GoodsModel;
use app\api\model\GoodsUserRanksPrice as RanksPriceModel;
use app\api\model\GoodsSkuUserRanksPrice as SkuRankPriceModel;
use app\api\model\GoodsSku as GoodsSkuModel;

use think\Validate;
use think\Request;
use think\Db;

class Goods extends Base
{
    //获取轮播商品
    public function getRollGoods(Request $request)
    {
        $rule = [
            'goods_num'     =>  'require|number',
        ];
        $message = [
            'goods_num.require'     => '请求必须携带数量',
            'goods_num.number'      => '请求的数量必须为整型数字',
        ];
        $data = $request->param();
        $validate = new Validate($rule,$message);
        if (!$validate->check($data)){
            return json(['code'=>0,'data'=>$validate->getError()]);
        }
        $goods_model = new GoodsModel();
        $res = $goods_model->where(['is_roll'=>1])
            ->where(['delete_time'=>0])
            ->order('create_time','desc')
            ->limit($data['goods_num'])
            ->field('id,roll_pic')
            ->select()->toArray();
        return json(['code'=>1,'data'=>$res]);
    }

    //获取最新商品
    public function getNewGoods(Request $request)
    {
        $rule = [
            'offset'        =>  'require',
            'length'        =>  'require',
        ];
        $message = [
            'offset.require'     => '请求必须携带offset',
            'length.require'     => '请求必须携带length',
        ];
        $data = $request->param();
        $validate = new Validate($rule,$message);
        if (!$validate->check($data)){
            return json(['code'=>0,'data'=>$validate->getError()]);
        }
        $goods_model = new GoodsModel();
        $res = $goods_model->order('create_time','desc')
            ->where(['delete_time'=>0])
            ->limit($data['offset'],$data['length'])
            ->field('id,is_attr,show_price,thumb_img,name,price')
            ->select()->toArray();

        foreach ($res as &$key){
            if ($key['is_attr'] == 1) {          //开启属性
                $key['price'] = $key['show_price'];
                unset($key['show_price']);
            }else{                               //关闭属性
                unset($key['show_price']);
            }
        }

        if (count($res)){
            return json(['code'=>1,'data'=>$res]);
        }else{
            return json(['code'=>0,'data'=>'没有更多了']);
        }
    }

    //获取分类下商品
    public function getCateGoods(Request $request)
    {
        $rule = [
            'cate_id'  => 'require',
            'offset'   => 'require',
            'length'   => 'require',
        ];

        $message = [
            'cate_id.require'      => '分类id必须填写',
            'offset.require'       => '偏移量必须填写',
            'length.require'       => '数据长度必须填写',
        ];
        $data = $request->param();
        $goods_model = new GoodsModel();
        $validate = new Validate($rule,$message);

        if (!$validate->check($data)) {
            return json(['code' => 0, 'data' => $validate->getError()]);
        }
        $data = $goods_model->where(['delete_time'=>0])->where(['cate_id'=>$data['cate_id']])->order('all_volume','desc')->order('create_time','desc')->limit($data['offset'],$data['length'])->field('thumb_img,name,price,is_attr,show_price,id')->select()->toArray();
        foreach ($data as &$key){
            if ($key['is_attr'] == 1) {          //开启属性
                $key['price'] = $key['show_price'];
                unset($key['show_price']);
            }else{                               //关闭属性
                unset($key['show_price']);
            }
        }
        if (count($data)){
            return json(['code'=>1,'data'=>$data]);
        }else{
            return json(['code'=>0,'data'=>'没有数据']);
        }
    }

    //获取商品详细信息
    public function getGoodsInfo(Request $request)
    {
        $goods_id = $request->param('goods_id');
        if (!$goods_id){
            return json(['code'=>0,'msg'=>'缺少商品id']);
        }
        $goods_model = new GoodsModel();
        $goods_sku_model = new GoodsSkuModel();
        $sku_rank_price_model = new SkuRankPriceModel();
        $goods_user_rank_model = new RanksPriceModel();
        $temp = $goods_model->field('is_attr')->find($goods_id);

        if ($temp['is_attr'] == 1){
            $data = $goods_model->where(['id'=>$goods_id])->field('id,detail_img,name,desc,show_price price,all_volume,goods_attr,goods_attr_val,attr_val_ids')->find()->toArray();
            $goods_attr = json_decode($data['goods_attr'],true);
            $goods_attr_val = json_decode($data['goods_attr_val'],true);
            $attr_val_ids = explode(',',$data['attr_val_ids']);

            //根据ids获取属性
            foreach ($attr_val_ids as $key => $value){
                foreach ($goods_attr_val as $key1){
                    if ($value == $key1['id']){
                        $attribute[] = $key1;       //值
                    }
                }
            }
            //属性 与 属性值 合在一起
            foreach ($goods_attr as &$key){
                foreach ($attribute as $key1){
                    if ($key['id'] == $key1['attr_id']){
                        $key['val'][] = $key1;
                    }
                }
                if (!isset($key['val'])){
                    unset($key);
                    continue;
                }
                //分门别类存储    type ： 1  商品参数  type ： 2 用户选择属性
                if ($key['type'] == 1){
                    $data['attribute']['one'][] = $key;
                }elseif($key['type'] == 2){
                    $data['attribute']['two'][] = $key;
                }
            }
            unset($key);
            //查询 规则组合 的 售价 会员售价 库存
            $goods_sku = $goods_sku_model->alias('sku')->where(['goods_id'=>$goods_id,'delete_time'=>0])->field('sku.id sku_id,sku.sku_group,sku.goods_num sku_goods_num,sku.sales_volume sku_sales_volume,sku.goods_price sku_goods_price')->select()->toArray();
            foreach ($goods_sku as &$key){
                $temp = $sku_rank_price_model->alias('sku_rank_price')
                                         ->join('cake_user_ranks rank','rank.id = sku_rank_price.rank_id')
                                        ->field('sku_rank_price.price rank_price,rank.id rank_id,rank.rank_name')
                                        ->where(['sku_rank_price.goods_sku_id'=>$key['sku_id']])
                                        ->where(['sku_rank_price.delete_time'=>0])
                                        ->where(['rank.delete_time'=>0])
                                        ->select()->toArray();
                $key['rank_price'] = $temp;
            }
            unset($data['goods_attr']);
            unset($data['goods_attr_val']);
            $data['sku'] = $goods_sku;
        }else{
            $data = $goods_model->where(['id'=>$goods_id])->field('id,detail_img,name,desc,price,all_volume')->find();
            $data['attribute'] = [];
            $rank_price = $goods_user_rank_model->alias('rank_price')
                                    ->join('cake_user_ranks rank','rank_price.rank_id = rank.id')
                                    ->where(['rank_price.goods_id'=>$goods_id])
                                    ->where(['rank.delete_time'=>0])
                                    ->field('rank_price.id goods_user_rank_price_id,rank.rank_name rank_name,rank_price.price rank_price')
                                    ->select();
            $data['sku'] = $rank_price;
        }
        if (!$data){
            return json(['code'=>0,'data'=>'不存在此商品']);
        }
        $data['detail_img'] = explode(',',$data['detail_img']);
        return json(['code'=>1,'data'=>$data]);
    }

    //根据选择的属性筛选出商品       商品名称 价格 封面图 id
    public function getAttrValGoods(Request $request)
    {
        $rule = [
            'screen_val_ids' => 'require',
            'offset'   => 'require',
            'length'   => 'require',
        ];
        $message = [
            'screen_val_ids.require'     => '筛选规格ids必须填写',       //此处改为screen_ids
            'offset.require'       => '偏移量必须填写',
            'length.require'       => '数据长度必须填写',
        ];
        $data = $request->param();
        $validate = new Validate($rule,$message);

        if (!$validate->check($data)) {
            return json(['code' => 0, 'data' => $validate->getError()]);
        }

        $sql = ' select id goods_id,price,show_price,thumb_img,`name` goods_name,is_attr from cake_goods where delete_time = 0 ';
        $screen_val = explode(',',$data['screen_val_ids']);
        foreach ($screen_val as $key => $value){
            $sql .= " and FIND_IN_SET('{$value}',screen_val_ids)";
        }
        $sql .= " order by create_time desc limit {$data['offset']},{$data['length']}";

        $res = Db::query($sql);
        foreach ($res as &$key){
            if ($key['is_attr'] == 1) {          //开启属性
                $key['price'] = $key['show_price'];
                unset($key['show_price']);
            }else{                               //关闭属性
                unset($key['show_price']);
            }
        }
        return json(['code'=>1,'data'=>$res]);
    }
}