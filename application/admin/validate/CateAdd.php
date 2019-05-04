<?php

namespace app\admin\validate;

use think\Validate;

class CateAdd extends Validate
{
	protected $rule = [
	    'pid'       => 'require',
        'depth'     => 'require',
        'name'      => 'require',
        'img_url'   => 'require',
    ];
    

    protected $message = [
        'pid.require'   => '必须选择父级节点',
        'depth.require' => '操作非法',
        'name.require'  => '必须填写分类名称',
        'img_url.require'   => '封面图片必须上传',
    ];
}
