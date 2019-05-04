<?php

namespace app\admin\controller;

use think\Controller;
use think\Exception;
use think\Request;
use app\admin\model\Category as CateModel;
use app\admin\model\CategoryAttr as CateAttrModel;
use app\admin\model\CategoryAttrVal as CateAttrValModel;

use app\admin\validate\AttrAdd as AttrAddValidate;

class Attribute extends Base
{
    public function index(Request $request)
    {
        $cate_attr_model = new CateAttrModel();
        if ($data = $request->post() && $request->isAjax()) {    //异步请求获取某分类下属性
            $cate_id = $request->post('cate_id');
            $data = $cate_attr_model->alias('a')
                ->join('cake_category c', 'c.id = a.cate_id')
                ->field('a.*,c.name cate_name')
                ->where('a.delete_time = 0')
                ->where('a.cate_id  ='.$cate_id)
                ->select();
            $this->assign('data',$data);
            $html = $this->fetch('attribute/ajax_index');
            return json(['html'=>$html]);
        } else {      //刚进来
            $data = $cate_attr_model->alias('a')
                ->join('cake_category c', 'c.id = a.cate_id')
                ->field('a.*,c.name cate_name')
                ->where('a.delete_time = 0')
                ->select();
            $count = $cate_attr_model->where(['delete_time'=>0])->count();
            $this->assign('count',$count);
            $this->assign('data', $data);
            return $this->fetch();
        }
    }

    public function add(Request $request)
    {
        $cate_model = new CateModel();
        if (!$cate_id = $request->param('cate_id')) {
            die;
        }
        $cate_info = $cate_model->where(['id' => $cate_id])->find();
        $this->assign('cate_info', $cate_info);
        return $this->fetch();
    }

    public function addChange(Request $request)
    {
        $cate_attr_model = new CateAttrModel();
        $cate_attr_val_model = new CateAttrValModel();
        $data = $request->post();
        $validate = new AttrAddValidate();
        if (!$validate->check($data)) {
            return json(['code' => 0, 'msg' => $validate->getError()]);
        }
        if ($data['edit_type'] == 3){
            $data['value'] = '';
        }else{
            if (!$data['value']){
                return json(['code'=>0,'msg'=>'请输入属性值']);
            }
        }
        //新增数据      category_attr   与  category_attr_val
        $cate_attr_model->startTrans();
        try {
            $data['create_time'] = time();
            $data['delete_time'] = 0;
            $cate_attr_model->insert($data);
            $attr_id = $cate_attr_model->getLastInsID();
            if (!$attr_id) {
                throw new Exception('操作失误，请刷新后重新尝试',1004);
            }
            $attr_values = explode(',', $data['value']);
            $attr_vals = [];
            foreach ($attr_values as $key => $value) {
                $attr_vals[] = [
                    'attr_id' => $attr_id,
                    'attr_value' => $value,
                ];
            }
            $res = $cate_attr_val_model->saveAll($attr_vals);
            if (!$res) {
                throw new Exception('操作失误，请刷新后重新尝试',1004);
            }
            $cate_attr_model->commit();
        } catch (Exception $e) {
            $cate_attr_model->rollback();
            return json(['code' => 0, 'msg' => $e->getMessage()]);
        }
        return json(['code' => 1, 'msg' => '操作成功']);
    }

    public function edit(Request $request)
    {
        $attr_id = $request->param('id');
        $attr_model = new CateAttrModel();
        $cate_model = new CateModel();
        $data = $attr_model->find($attr_id);
        $cate_info = $cate_model->find($data['cate_id']);
        $this->assign('data',$data);
        $this->assign('cate_info',$cate_info);
        return $this->fetch();
    }

    public function editChange(Request $request)
    {
        if ($data = $request->post()){
            $validate = new AttrAddValidate();
            $attr_model = new CateAttrModel();
            $attr_val_model = new CateAttrValModel();
            if (!$validate->check($data)){
                return json(['code'=>0,'msg'=>$validate->getError()]);
            }
            if (!isset($data['id'])){
                return json(['code'=>0,'msg'=>'非法请求']);
            }
            if ($data['edit_type'] == 3){
                $data['value'] = '';
            }else{
                if (!$data['value']){
                    return json(['code'=>0,'msg'=>'请输入属性值']);
                }
            }
            $data['update_time'] = time();
            $attr_model->startTrans();
            try{
                //查询旧数据 category_attr
                $old_data = $attr_model->find($data['id']);

                //修改 category_attr
                $res = $attr_model->where(['id'=>$data['id']])->update($data);
                if (!$res){
                    throw new Exception('操作失误，请刷新后重新尝试',1004);
                }
                //删除 category_attr_val表旧数据
                $res = $attr_val_model->whereIn('attr_value',$old_data['value'])->delete();
                if (!$res){
                    throw new Exception('操作失误，请刷新后重新尝试',1004);
                }
                //新增 category_attr_val表数据
                $temp_data = [];
                $temp_values = explode(',',$data['value']);
                foreach ($temp_values as $key => $value){
                    $temp_data[] = [
                        'attr_id'   => $data['id'],
                        'attr_value'=> $value,
                    ];
                }
                $res = $attr_val_model->saveAll($temp_data);
                if (!$res){
                    throw new Exception('操作失误，请刷新后重新尝试',1004);
                }
                $attr_model->commit();
            }catch (Exception $e){
                $attr_model->rollback();
                return json(['code'=>0,'msg'=>$e->getMessage()]);
            }

            return json(['code'=>1,'msg'=>'修改成功！']);
        }
    }

    //删除
    public function delete(Request $request)
    {
        if ($id = $request->param('id')){
            $cate_attr_model = new CateAttrModel();
            $cate_attr_model->where(['id'=>$id])->update(['delete_time'=>time()]);
            return json(['code'=>1,'msg'=>'删除成功']);
        }
    }
}

