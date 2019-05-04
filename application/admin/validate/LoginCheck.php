<?php
/**
 * Created by PhpStorm.
 * User: fycy
 * Date: 2018/10/26
 * Time: 13:53
 */

namespace app\admin\validate;

use think\Validate;

class LoginCheck extends Validate
{
    protected $rule = [
        'captcha'       => 'require',
        'username'      => 'require',
        'password'      => 'require'
    ];

    protected $message = [
        'captcha.require'       => '验证码必须填写',
        'username.require'      => '用户名必须填写',
        'password.require'      => '密码必须填写',
    ];

}