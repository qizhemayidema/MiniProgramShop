<?php

namespace app\admin\controller;

use MongoDB\Driver\Manager;
use think\App;
use think\Controller;
use think\Request;
use app\admin\validate\LoginCheck as LoginCheckValidate;
use app\admin\model\Manager as ManagerModel;
use think\validate;

class Login extends Controller
{
    public function __construct(App $app = null)
    {
        parent::__construct($app);
        if (session('admin.user_info')){
            return $this->redirect(url('admin/index/index'));
        }
    }

    public function index()
    {
        return $this->fetch();
    }

    public function check(Request $request)
    {
        $data = $request->post();
        $validate = new LoginCheckValidate();
        if (!$validate->check($data)){
            return json(['code'=>0,'msg'=>$validate->getError()]);
        }else{
            if (!captcha_check($data['captcha'])){
                return json(['code'=>0,'msg'=>'验证码错误']);
            }
            $manager_model = new ManagerModel;
            $res = $manager_model->where(['username'=>$data['username'],'password'=>md5($data['password'])])->find();
            if (!$res){
                return json(['code'=>0,'msg'=>'帐号或密码错误']);
            }
            session('admin.user_info',$res);
            return json(['code'=>1,'msg'=>'']);
        }
    }
}

