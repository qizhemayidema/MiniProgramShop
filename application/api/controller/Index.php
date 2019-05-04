<?php
/**
 * Created by PhpStorm.
 * User: fycy
 * Date: 2018/11/12
 * Time: 16:54
 */

namespace app\api\controller;

use app\api\model\User as UserModel;

use think\Request;

class Index extends Base
{
    //程序初始化
    public function onLoad(Request $request)
    {
        $user_model = new UserModel();

        $token = $request->post('token');
        if (!$token){
            return json(['code'=>0,'data'=>'error']);
        }
        $this->checkUserRank($token);     //检查会员等级
        $this->checkUserCardOrder($token);//检查作废的现金卡订单
                                            //检查过期商品订单
        $user_model->where(['token'=>$token])->update(['last_login_time'=>time()]);//用户登录最后时间

        return json(1);
    }
}