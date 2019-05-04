<?php

namespace app\admin\controller;

use think\Controller;
use think\Request;

use app\admin\model\UserRanks as RankModel;

use app\admin\validate\UserRanksAdd as UserRanksAddValidate;

//用户会员控制器
class UserRanks extends Base
{
    //用户会员控制器
    public function index()
    {
        $rank_model = new RankModel();
        $data = $rank_model->where(['delete_time'=>0])->select();
        $this->assign('data',$data);
        return $this->fetch();
    }

    public function add()
    {
        return $this->fetch();
    }

    public function addChange(Request $request)
    {
        $data = $request->post();
        $rank_model = new RankModel();
        $validate = new UserRanksAddValidate();
        if (!$validate->check($data)){
            return json(['code'=>0,'msg'=>$validate->getError()]);
        }
        unset($data['file']);
        $data['create_time'] = time();
        $rank_model->save($data);
        return json(['code'=>1,'msg'=>'添加成功']);
    }

    public function edit(Request $request)
    {
        $id = $request->param('id');
        $rank_model = new RankModel();
        $data = $rank_model->find($id);
        $this->assign('data',$data);
        return $this->fetch();
    }

    public function editChange(Request $request)
    {
        $data = $request->post();
        $rank_model = new RankModel();
        $validate = new UserRanksAddValidate();
        if (!$validate->check($data)){
            return json(['code'=>0,'msg'=>$validate->getError()]);
        }
        if (!isset($data['id'])){
            return json(['code'=>0,'msg'=>'非法操作']);
        }
        if ($data['start_score'] > $data['end_score']){
            return json(['code'=>0,'msg'=>'开始积分要小于结束积分']);
        }
        unset($data['file']);
        $rank_model->where(['id'=>$data['id']])->update($data);
        return json(['code'=>1,'msg'=>'修改成功']);
    }

    public function delete(Request $request)
    {
        $id = $request->param('id');
        $rank_model = new RankModel();
        $rank_model->where(['id'=>$id])->update(['delete_time'=>time()]);
        return json(['code'=>1,'msg'=>'删除成功']);
    }
}
