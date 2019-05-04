<?php

namespace app\admin\controller;

use think\Request;

use app\admin\model\User as UserModel;
use app\admin\model\UserRanks as UserRankModel;

class User extends Base
{
    //用户列表
    public function index()
    {
        $user_model = new UserModel();
        $user_rank_model = new UserRankModel();

        $user_rank_info = $user_rank_model->column('id,rank_name');

        $user_data =  $user_model->field('openid,nick_name,avatar_url,gender,country,province,city,all_shop,all_score,rank_id,money,last_login_time')->order('id','desc')->paginate(20);

        $this->assign('user_rank',$user_rank_info);
        $this->assign('user_data',$user_data);

        return $this->fetch();
    }
}
