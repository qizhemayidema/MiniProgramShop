<?php

namespace app\admin\controller;

use think\Controller;
use app\admin\model\Category as CateModel;
use app\admin\model\UserRanks as RanksModel;
use app\admin\model\GoodsSku as GoodsSkuModel;
use app\admin\model\GoodsSkuUserRanksPrice as SkuUserRanksPriceModel;
use think\Request;

class Base extends Controller
{
    protected $middleware = ['LoginCheck'];

    //获取节点
    public function getCate()
    {
        $obj = new CateModel();
        $res = $obj->field('id,pid,name,img_url')->select()->toArray();
        array_unshift($res,['id'=>0,'pid'=>0,'name'=>'顶级节点']);
        return json($res);
    }

    //uploader批量上传图片
    public function uploaders(Request $request)
    {
        if ($request->isPost()){
            $file = $_FILES['file'];
//            return json($file['name']);
//            $name = uniqid(true).mb_substr($file['name'],strrpos($file['name'],'.'));
            $file_path = '/static/images/';
            $file_info = $request->file('file')->move('.'.$file_path);
            $path = $file_info->getSaveName();
            $path = str_replace('\\','/',$path);
            $file_path .= $path;

            return json(['valid'=>1,'message'=>$file_path]);
        }
    }

    //查询会员信息
    public function selectRanks(Request $request)
    {
        $rank_model = new RanksModel();
        $rank_info = $rank_model->where(['delete_time' => 0])->field('id,rank_name')->select();
        return json($rank_info);
    }

    //查找某个商品的sku信息 以及验证
    public function selectGoodsSku(Request $request)
    {
        $goods_id = $request->param('goods_id');

        $goods_sku_model = new GoodsSkuModel();
        $user_rank_model = new RanksModel();
        $sku_user_rank_price_model = new SkuUserRanksPriceModel();

        $sku_data = $goods_sku_model->where(['goods_id'=>$goods_id,'delete_time'=>0])->select()->toArray();
        $sku_rank_price = $sku_user_rank_price_model->where(['goods_id'=>$goods_id,'delete_time'=>0])->select()->toArray();

        $rank_ids = $user_rank_model->where(['delete_time'=>0])->column('id');

        foreach ($sku_rank_price as $key => $value){
            if (!in_array($value['rank_id'],$rank_ids)){
                $sku_user_rank_price_model->where(['id'=>$value['id']])->delete();
                unset($sku_rank_price[$key]);
            }
        }
        foreach ($sku_data as $key => &$value){
            foreach ($sku_rank_price as $key1 => $value1){
                if ($value['id'] == $value1['goods_sku_id']){
                    $value['rank_price'][] = $value1;
                }
            }
        }
        return json($sku_data);
    }
}

/*
 * 1、根据goods表中的
 *
 * */



















