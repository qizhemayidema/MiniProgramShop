<?php

namespace app\admin\controller;

use think\Controller;
use think\Request;
use app\admin\validate\CateAdd as CateAddValidate;
use app\admin\validate\CategoryEdit as CateEditValidate;
use app\admin\model\Category as CateModel;

//分类控制器
class Category extends Base
{
    public function index()
    {
        return $this->fetch();
    }

    public function addChange(Request $request)
    {
        if ($data = $request->post()){
            $validate = new CateAddValidate();
            $cate_model = new CateModel();
            if (!$validate->check($data)) {
                return json(['code' => 0, 'msg' => $validate->getError()]);
            }
            $cate_model->save($data);

            return json(['code'=>1,'msg'=>'添加成功']);
        }
    }

    public function editChange(Request $request)
    {
        $data = $request->post();
        $validate = new CateEditValidate();
        $cate_model = new CateModel();
        if (!$validate->check($data)){
            return json(['code'=>0,'msg'=>$validate->getError()]);
        }
        $cate_model->where(['id'=>$data['id']])->update(['name'=>$data['name'],'img_url'=>$data['img_url']]);
        return json(['code'=>1,'msg'=>'修改成功']);
    }

    public function delete()
    {

    }
}
