<?php

namespace app\admin\validate;

use think\Validate;

class AttrAdd extends Validate
{
	protected $rule = [
        'cate_id'       => 'require',
        'edit_type'     => 'require',
        'name'          => 'require',
        'type'          => 'require',
    ];

    protected $message = [
        'cate_id.require'       => '操作非法',
        'name.require'          => '属性名称必须填写',
        'edit_type.rqeuire'     => '录入类型必须填写',
        'type.require'          => '类别必须填写',
    ];
}
