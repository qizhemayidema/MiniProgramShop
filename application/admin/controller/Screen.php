<?php

namespace app\admin\controller;

//规格筛选控制器
use think\Exception;
use think\Request;
use think\Validate;

use app\admin\model\Category as CateModel;
use app\admin\model\CategoryScreen as CateScreenModel;
use app\admin\model\CategoryScreenVal as CateScreenValModel;

class Screen extends Base
{

    public function index()
    {
        $screen_model = new CateScreenModel();
        $data = $screen_model->alias('screen')
                            ->join('category cate','screen.cate_id = cate.id')
                            ->field('screen.*,cate.name cate_name')
                            ->where(['screen.delete_time'=>0])
                            ->select();
        $this->assign('data',$data);
        return $this->fetch();
    }

    public function add(Request $request)
    {
        $cate_id = $request->param('cate_id');
        if (!$cate_id){
            return json(['code'=>0,'msg'=>'err']);
        }
        $cate_model = new CateModel();
        $cate_info = $cate_model->where(['id'=>$cate_id])->field('id,name')->find();
        $this->assign('cate_info',$cate_info);
        return $this->fetch();
    }

    public function addChange(Request $request)
    {
        $data = $request->post();

        $rule = [
            'cate_id'       => 'require',
            'screen_name'   => 'require',
            'value'         => 'require',
        ];

        $message = [
            'cate_id.require'       => '请求非法',
            'screen_name.require'   => '筛选名不能为空',
            'value.require'         => '筛选值不能为空'
        ];
        $validate = new Validate($rule,$message);
        $screen_model = new CateScreenModel();
        $screen_val_model = new CateScreenValModel();
        if (!$validate->check($data)){
            return json(['code'=>0,'msg'=>$validate->getError()]);
        }
        $screen_model->startTrans();
        try{
            //入库 screen
            $screen_model->insert([
                'cate_id'       => $data['cate_id'],
                'screen_name'   => $data['screen_name'],
                'value'         => $data['value'],
                'create_time'   => time(),
            ]);

            $screen_id = $screen_model->getLastInsID();

            $screen_val = [];
            $value_val = explode(',',$data['value']);
            foreach ($value_val as $key => $value){
                $screen_val[] = [
                    'screen_id' => $screen_id,
                    'screen_val'=> $value,
                ];
            }
            //入库 screen val
            $screen_val_model->insertAll($screen_val);

            $screen_model->commit();
        }catch (Exception $e){
            $screen_model->rollback();
            return json(['code'=>0,'msg'=>$e->getMessage()]);
        }

        return json(['code'=>1,'msg'=>'添加成功']);
    }

    public function edit(Request $request)
    {
        $screen_model = new CateScreenModel();

        $screen_id = $request->param('id');
        $data = $screen_model->alias('screen')
            ->join('category cate','screen.cate_id = cate.id')
            ->field('screen.*,cate.name cate_name')
            ->where(['screen.id'=>$screen_id])
            ->find();
        $this->assign('data',$data);
        return $this->fetch();
    }

    public function editChange(Request $request)
    {
        $rule = [
            'id'        => 'require',
            'screen_name'   => 'require',
            'value'     => 'require',
        ];

        $message = [
            'id.require'        => '操作非法',
            'screen_name.require' => '操作非法',
            'value.require'     => '操作非法'
        ];

        $data = $request->post();
        $screen_model = new CateScreenModel();
        $screen_val_model = new CateScreenValModel();
        $validate = new Validate($rule,$message);
        if (!$validate->check($data)){
            return json(['code'=>0,'msg'=>$validate->getError()]);
        }
        $screen_model->startTrans();
        try{
            //尝试修改数据 如果返回false 则表明数据没有变动
            $res = $screen_model->where(['id'=>$data['id']])->update([
                'screen_name'   => $data['screen_name'],
                'value'         => $data['value'],
            ]);
            if (!$res){
                return json(['code'=>1,'msg'=>'数据没有变动']);
            }
            //首先删除 screen_val表中该screen_id的数据
            $screen_val_model->where(['screen_id'=>$data['id']])->delete();

            //新增数据到 screen_val
            $screen_val_arr = explode(',',$data['value']);
            $screen_val = [];
            foreach ($screen_val_arr as $key => $value){
                $screen_val[] = [
                    'screen_id' => $data['id'],
                    'screen_val'  => $value,
                ];
            }
            $screen_val_model->insertAll($screen_val);
            $screen_model->commit();
        }catch (Exception $e){
            $screen_model->rollback();
            return json(['code'=>0,'msg'=>$e->getMessage()]);
        }

        return json(['code'=>1,'msg'=>'修改成功']);
    }

    public function delete(Request $request)
    {
        $screen_id = $request->param('id');
        if (!$screen_id){
            return json(['code'=>0,'msg'=>'err']);
        }
        $screen_model = new CateScreenModel();
        $screen_model->where(['id'=>$screen_id])->update([
            'delete_time' => time(),
        ]);

        return json(['code'=>1,'msg'=>'删除成功']);

    }




}
