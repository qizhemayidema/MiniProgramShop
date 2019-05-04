<?php

namespace app\admin\validate;

use think\Validate;

class GoodsAdd extends Validate
{

	protected $rule = [
	    'cate_id'       => 'require',
        'name'          => 'require',
        'is_roll'       => 'require',
        'thumb_img'     => 'require',
        'detail_img'    => 'require',
        'desc'          => 'require',

    ];

    protected $message = [
        'cate_id.require'       => '操作非法',
        'name.require'          => '商品名称必须填写',
        'is_roll.require'       => '是否轮播必须选择',
        'thumb_img.require'     => '商品封面必须上传',
        'detail_img.require'    => '商品详情必须上传',
        'desc.require'          => '商品介绍必须填写',
    ];
}
