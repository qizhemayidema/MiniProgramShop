<?php
/**
 * Created by PhpStorm.
 * User: fycy
 * Date: 2018/11/2
 * Time: 10:37
 */

namespace app\api\controller;

use think\Request;

use app\api\model\Category as CateModel;
use app\api\model\CategoryAttr as AttrModel;
use app\api\model\CategoryAttrVal as AttrValModel;
use app\api\model\CategoryScreen as CateScreenModel;
use app\api\model\CategoryScreenVal as CatteScreenValModel;
use think\Validate;

class Category extends Base
{
    //获取一级分类
    public function getCateOne(Request $request)
    {
        $cate_model = new CateModel();
        $data = $cate_model->where(['depth'=>1])->field('id,name,img_url')->select();
        return json(['code'=>1,'data'=>$data]);

    }

    //获取二级分类(某个一级分类下的)
    public function getCateTwo(Request $request)
    {
        $cate_id = $request->param('cate_id');
        $cate_model = new CateModel();
        $data = $cate_model->where(['pid'=>$cate_id])->field('id,name,img_url')->select();
        return json(['code'=>1,'data'=>$data]);
    }

    //获取某二级分类下的属性 和属性值
    public function getCateTwoAttr(Request $request)
    {
        $rule = [
            'cate_id'       => 'require',
        ];

        $message = [
            'cate_id.require'   => '请携带cate_id',
        ];
        $attr_model = new AttrModel();
        $attr_val_model = new AttrValModel();
        $validate = new Validate($rule,$message);
        $data = $request->post();
        if (!$validate->check($data)){
            return json(['code'=>0,'data'=>$data]);
        }

        //查出属性数据
        $attr = $attr_model->where(['delete_time'=>0])->where(['cate_id'=>$data['cate_id']])->where('edit_type','<>',3)->field('id attr_id,name attr_name')->select()->toArray();
        //查出属性值数据
        $attr_ids = [];
        foreach ($attr as $key){
            $attr_ids[] = $key['attr_id'];
        }
        $attr_val = $attr_val_model->whereIn('attr_id',$attr_ids)->field('id attr_val_id,attr_id,attr_value')->select()->toArray();
        foreach ($attr as &$key){
            foreach ($attr_val as $key1){
                if ($key1['attr_id'] == $key['attr_id']){
                    $key['val'][] = $key1;
                }
            }
        }
        return json(['code'=>1,'data'=>$attr]);
    }

    //获取某个二级分类下筛选规格
    public function getScreenVal(Request $request)
    {
        $rule = [
            'cate_id' => 'require',
        ];

        $message = [
            'cate_id.require'       => '请携带cate_id',
        ];

        $data = $request->post();
        $screen_model = new CateScreenModel();
        $screen_val_model = new CatteScreenValModel();
        $validate = new Validate($rule,$message);
        if (!$validate->check($data)){
            return json(['code'=>0,'data'=>$validate->getError()]);
        }
        //查询筛选规格信息
        $screen_info = $screen_model->where(['delete_time'=>0,'cate_id'=>$data['cate_id']])->field('id,screen_name')->select()->toArray();
        $screen_ids = array_column($screen_info,'id');
        $screen_val_info = $screen_val_model->whereIn('screen_id',$screen_ids)->select()->toArray();
        foreach ($screen_info as &$key){
            foreach ($screen_val_info as $key1){
                if ($key1['screen_id'] == $key['id']){
                    $key['val'][] = $key1;
                }
            }
        }

        return json(['code'=>1,'data'=>$screen_info]);
    }
}
