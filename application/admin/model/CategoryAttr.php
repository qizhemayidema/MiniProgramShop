<?php

namespace app\admin\model;

use think\Model;
use app\admin\model\CategoryAttrVal as AttrValModel;

class CategoryAttr extends Model
{
    public function getTypeAttr($value)
    {
        //1 :  商品参数  2 ： 用户选择属性',
        $res = [1 => '商品参数' , 2 => '用户选择属性'];
        return $res[$value];
    }

    public function getEditTypeAttr($value)
    {
        //录入类型 1 ： 多选框  2 ：下拉框
        $res = [ 1 => '多选框', 2 => '下拉框',3 => '文本框'];
        return $res[$value];
    }

    //获取某个分类下的属性与属性值
    public function getCateAttrOne($cate_id)
    {
        $attr = $this->where(['cate_id' => $cate_id])->where(['delete_time'=>0])->select()->toArray();
        $attr_ids = [];
        foreach ($attr as $key) {
            $attr_ids[] = $key['id'];
        }
        $attr_ids = implode(',', $attr_ids);
        $attr_val_model = new AttrValModel();
        $attr_vals = $attr_val_model->whereIn('attr_id', $attr_ids)->select()->toArray();

        foreach ($attr as &$key) {
            foreach ($attr_vals as $key1) {
                if ($key['id'] == $key1['attr_id']) {
                    $key['item'][] = $key1;
                }
            }
        }
        return $attr;
    }

    //合成商品里的属性与属性值到一个数组
    public function goodsAttrMerge($attr,$attr_val)
    {
        $attr_ids = [];
        foreach ($attr as $key) {
            $attr_ids[] = $key['id'];
        }
        foreach ($attr as &$key) {
            foreach ($attr_val as $key1) {
                if ($key['id'] == $key1['attr_id']) {
                    $key['item'][] = $key1;
                }
            }
        }
        return $attr;
    }

}
