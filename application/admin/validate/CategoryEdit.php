<?php

namespace app\admin\validate;

use think\Validate;

class CategoryEdit extends Validate
{

	protected $rule = [
	    'id'    => 'require',
        'name'  => 'require',
    ];

    protected $message = [
        'id.require'    => '非法请求',
        'name.require'  => '名称必须填写',
    ];
}
