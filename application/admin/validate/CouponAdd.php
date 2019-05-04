<?php
/**
 * Created by PhpStorm.
 * User: fycy
 * Date: 2018/11/2
 * Time: 13:32
 */

namespace app\admin\validate;

use think\Validate;

class CouponAdd extends Validate
{
    protected $rule = [
        'name'             => 'require',
        'put_type'         => 'require',
        'type'             => 'require',
        'is_all'           => 'require',
        'money'            => 'require',
        'put_time'         => 'require',
        'put_end_time'     => 'require',
        'start_time'       => 'require',
        'end_time'         => 'require',
    ];

    protected $message = [
        'name.require'          => '名称必须填写',
        'put_type.require'      => '发放类型必须填写',
        'type.require'          => '优惠券类型必须填写',
        'is_all.require'        => '是否全场可用必须填写',
        'money.require'         => '优惠力度必须填写',
        'put_time.require'      => '发放开始时间必须填写',
        'put_end_time.require'  => '发放截止日期必须填写',
        'start_time.require'    => '使用开始日期必须填写',
        'end_time.require'      => '使用截止日期必须填写',
    ];
}