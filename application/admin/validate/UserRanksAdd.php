<?php

namespace app\admin\validate;

use think\Validate;

class UserRanksAdd extends Validate
{

	protected $rule = [
	    'rank_name'     => 'require',
        'start_score'   => 'require|number',
        'rank_img'      => 'require',
    ];

    protected $message = [
        'rank_name.require'     => '等级名称必须填写',
        'start_score.require'   => '等级开始积分必须填写',
        'start_score.number'    => '等级开始积分必须是整型数字',
        'end_score.number'      => '等级结束积分必须是整型数字',
        'rank_img.require'      => '等级图像标识必须上传',
    ];
}
