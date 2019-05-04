<?php
/**
 * Created by PhpStorm.
 * User: fycy
 * Date: 2018/11/5
 * Time: 9:27
 */

namespace app\api\controller;

use think\Request;

use app\api\model\User as UserModel;
use think\Validate;

class Login extends Base
{
    public function login(Request $request)
    {
        $timestamp = time();                                     //当前时间戳

        $all_data = $request->param();

        $rule = [
            'js_code'   => 'require',
            'userInfo'  => 'require',
        ];

        $message = [
            'js_code.require'   =>  '请求请携带js_code参数',
            'userInfo.require'  => '请求请携带userInfo参数',
        ];

        $validate = new Validate($rule,$message);
        if (!$validate->check($all_data)){
            return json(['code'=>0,'data'=>$validate->getError()]);
        }
        $code = $all_data['js_code'];
        $res = $this->getOpenIdAndSessionKey($code);
        if ($res['code'] == 1){
            //判断是否入库
            /*
             * token加密 当前时间戳加openid加盐值加随机数
             *
             * */
            $user_model = new UserModel();
            if (!$user_info = $user_model->where(['openid'=>$res['data']['openid']])->find()){
                $token = $this->makeToken($timestamp,$res['data']['openid']);
                $user_info = [
                    'openid'    => $res['data']['openid'],
                    'nick_name' => $all_data['userInfo']['nickName'],
                    'avatar_url'=> $all_data['userInfo']['avatarUrl'],
                    'gender'    => $all_data['userInfo']['gender'],
                    'token'     => $token,
                    'country'   => $all_data['userInfo']['country'],
                    'province'  => $all_data['userInfo']['province'],
                    'city'      => $all_data['userInfo']['city'],
                    'last_login_time'   => time(),
                ];
                $user_model->insert($user_info);
                $user_info['id'] = $user_model->getLastInsID();
            }else{
                $update_user_info = [];
                if ($timestamp - $user_info['last_login_time'] > 86400){
                    $update_user_info['token'] = $this->makeToken($timestamp,$res['data']['openid']);
                    $token = $update_user_info['token'];
                }else{
                    $token = $user_info['token'];
                }
                $update_user_info['last_login_time'] = time();
                $this->checkUserRank($user_info['token']);
                $user_model->where(['id'=>$user_info['id']])->update($update_user_info);
            }
            return json(['code'=>1,'token'=>$token]);
        }
        return json($res);
    }
}