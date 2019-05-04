--管理员表
create table `cake_manager`(
`id` int auto_increment,
`nickname` char(32) comment '管理员昵称',
`username` char(32) not null comment '用户名',
`password` char(32) not null comment '密码',
`visit_time` int(11) not null comment '最后访问时间',
primary key (`id`)
)engine=innodb charset=utf8;

--分类表
create table `cake_category`(
`id` int auto_increment,
`name` char(128) not null comment '分类名称',
`pid` int(11) not null comment '父级id',
`depth` tinyint(1) not null comment '深度 1 为第一级 2 为第二级',
`img_url` varchar(255) not null comment '分类封面',
primary key(`id`),
)engine=innodb charset=utf8;

--分类属性表
create table `cake_category_attr`(
`id` int auto_increment,
`cate_id` int(11) not null comment '分类id',
`name` char(128) not null comment '属性名称',
`type` tinyint(1) not null comment '属性的类别 1 :  商品参数  2 ： 用户选择属性',
`edit_type` tinyint(1) not null comment '录入类型 1 ： 多选框  2 ：下拉框   3 :  文本框',
`value` text comment '属性值 多个选项用逗号隔开',
`create_time` int(11) not null comment '创建时间',
`update_time` int(11) comment '修改时间',
`delete_time` int(11)comment '删除时间',
primary key(`id`)
)engine=innodb charset=utf8;

--分类属性值表
create table `cake_category_attr_val`(
`id` int auto_increment,
`attr_id` int(11) not null comment '分类属性id',
`attr_value` varchar(256) comment '属性值',
primary key(`id`)
)engine=innodb charset=utf8;

--筛选属性表
create table `cake_category_screen`(
`id` int auto_increment,
`cate_id` int(11) not null comment '分类id',
`screen_name` varchar(50) not null comment '筛选名称',
`value` text not null comment '属性值 用逗号隔开',
`create_time` int(11) not null comment '创建时间',
`delete_time` int(11) not null default 0 comment '删除时间',
primary key(`id`)
)engine=innodb charset=utf8;

--筛选属性值表
create table `cake_category_screen_val`(
`id` int auto_increment,
`screen_id` int(11) not null comment '筛选属性id',
`screen_val` text comment '筛选属性值',
primary key(`id`)
)engine =innodb charset=utf8;

--商品表
create table `cake_goods`(
`id` int auto_increment,
`cate_id` int(11) not null comment '分类id',
`name` varchar(128) not null comment '商品名称',
`desc` text not null comment '商品介绍',
`thumb_img` varchar(256) comment '商品封面',
`detail_img` text comment '商品详情图',
`is_attr` tinyint(1) not null comment '是否启动属性 0 否 1 是',
`num` int(11) comment '库存数量 只有关闭属性 此字段才有效',
`is_roll` tinyint(1) not null comment '是否轮播 0  否  1 是',
`is_commend` tinyint(1) not null default 0 comment '是否推荐 0 否 1 是  （此字段暂时不用）',
`roll_pic` varchar(256) comment '轮播图片',
`price` decimal(10,2) not null comment '单价 只有关闭属性 此字段才有效',
`show_price` varchar(256) not null comment '显示单价 只有开启属性 此字段才有效',
`goods_attr` text comment '商品属性 json',
`goods_attr_val` text comment '属性值 json',
`attr_val` text comment '所选中的属性值 具体的值',
`attr_val_ids` text comment '所选中的属性值id',
`screen_val_ids` text comment '所被选中筛选的属性值ids',
`all_volume` int(11) default 0 comment '总销量',
`create_time` int(11) not null comment '创建时间',
`update_time` int(11) comment '修改时间',
`delete_time` int(11) default 0 comment '删除时间',
primary key(`id`),
index(`delete_time`),
index(`is_roll`),
index(`is_attr`),
)engine=innodb charset=utf8;

--商品规格表 （价格 库存）
create table `cake_goods_sku`(
`id` int auto_increment,
`goods_id` int(11) not null comment '商品id',
`sku_group` text not null comment '商品sku组合',
`goods_num` int(11) not null comment '该组合库存',
`sales_volume` int(11) not null default 0 comment '该组合销量',
`goods_price` decimal(10,2) not null comment '该组合售价',
`thumb_img` varchar(256) comment '图片  暂时废弃',
`create_time` int(11) not null comment '创建时间',
`delete_time` int(11) not null default 0 comment '删除时间',
primary key(`id`),
index(`sku_group`),
index(`goods_id`)
)engine=innodb charset=utf8;

--商品规格会员价格表
create table `cake_goods_sku_user_ranks_price`(
`id` int auto_increment,
`goods_sku_id` int(11) not null comment '商品sku的id',
`rank_id` int(11) not null comment '会员级别id',
`goods_id` int(11) not null comment '商品id',
`price` decimal(10,2) not null comment '售价',
`create_time` int(11) not null comment '创建时间',
`delete_time` int(11) not null default 0 comment '删除时间',
primary key(`id`),
index(`goods_sku_id`),

)engine=innodb charset=utf8;

mysql> select * from cake_goods where FIND_IN_SET('1',attr_val_ids) and find_in_
set('4',attr_val_ids);  --此为商品筛选语句

--商品会员价格表
create table `cake_goods_user_ranks_price`(
`id` int auto_increment,
`goods_id` int(11) not null comment '商品id',
`rank_id` int(11) not null comment '会员等级id',
`price` decimal(10,2) not null comment '会员价格',
`create_time` int(11) not null comment '创建时间',
primary key(`id`)
)engine=innodb charset=utf8;

--会员等级表
create table `cake_user_ranks`(
`id` int auto_increment,
`rank_name` varchar(20) not null comment '等级名称',
`start_score` int(11) not null comment '等级开始积分',
`sort_score` int(11) not null comment '权重 1 最小 2 3...',
`rank_img` varchar(150) not null comment '等级图像标识',
`desc` text comment '备注',
`create_time` int(11) not null comment '创建时间',
`delete_time` int(11) default 0 comment '删除时间',
primary key(`id`)
)engine=innodb charset=utf8;

--评论表
create table `cake_goods_message`(
`id` int auto_increment,
`user_id` int not null comment '用户id',
`goods_id` int not null comment '商品id',
`score` float not null comment '商品评分',
`message` varchar(256) not null comment '商品留言',
primary key(`id`)
)engine=innodb charset=utf8;

--优惠券表
create table `cake_coupon`(
`id` int auto_increment,
`code` varchar(128) not null comment '优惠券编号',
`name` varchar(128) not null comment '优惠券名称',
`type` tinyint(1) not null comment '优惠券类型 1 满减   2  折扣券   3   现金券',
`put_type` tinyint(1) not null comment '发放类型 1 全场发放  2 用户领取   3   新用户注册',
`is_all`  tinyint(1)comment '是否全场可用 0  否  1 是  现金券忽略',
`goods_ids` text comment '优惠券可用的商品ids 如果全场可用此字段作废',
`money` decimal(10,2) comment '优惠力度 如果是满减 则此处为减少的金额的字段 如果是折扣 此处为 多少折 如果 为现金券 此处为金额',
`cond` decimal(10,2) comment '此处为 满减的 满多少的字段',
`count` int(11) comment '优惠券库存 只有 用户领取才有效',
`desc` text comment '备注',
`put_time` int(11) not null comment '发放日期',
`put_end_time` int(11) not null comment '发放结束时间',
`start_time` int(11) not null comment '使用开始日期',
`end_time` int(11) not null comment '使用截止日期',
`create_time` int(11) not null comment '创建日期',
`delete_time` int(11) default 0 comment '删除日期',
primary key(`id`)
)engine=innodb charset=utf8;


--用户表
create table `cake_user`(
`id` int auto_increment,
`openid` varchar(32) not null comment 'openid',
`nick_name` varchar(32) not null comment '昵称',
`avatar_url` varchar(256) not null comment '头像',
`gender` tinyint(1) not null comment '性别 1 男 2 女 0 未知',
`country` char(50) comment '国家',
`province` char(50) comment '省份',
`city` char(50) comment '城市',
`all_shop` decimal(10,2) not null default 0 comment '总消费额度',
`all_score` decimal(10,2) not null default 0 comment '总积分',
`rank_id` int(11) not null default 0 comment '会员id 默认 0 普通用户',
`token` char(32) not null comment '用户token标识',
`money` decimal(10,2) not null default 0.00 comment '账户余额',
`last_login_time` int(11) comment '最后登录时间',
primary key(`id`),
key `openid` (`openid`)
)engine=innodb charset=utf8;


--用户收货地址
create table `cake_user_address`(
`id` int auto_increment,
`user_id` int(11) not null comment '用户id',
`province` char(50) comment '省份',
`city` char(50) not null comment '城市',
`region` char(50) not null comment '区域',
`desc` varchar(256) not null comment '详细信息',
`user_name` char(50) not null comment '联系人姓名',
`user_phone` char(11) not null comment '联系人电话',
`user_telephone` char(20) comment '座机电话 选填',
`is_select` tinyint(1) not null default 0 comment '是否 选中',
`create_time` int(11) not null comment '创建时间',
primary key(`id`),
key `user_id`(`user_id`)
)engine=innodb charset=utf8;

--现金卡表

--现金卡 购买记录表

--优惠券用户记录表（领取 使用 过期）
create table `cake_coupon_history`(
`id` int auto_increment,
`coupon_id` int(11) not null comment '优惠券id',
`user_id` int(11) not null comment '用户id',
`type` tinyint(1) not null comment '1 ： 领取 2 ：使用 3 冻结',
`time` int(11) not null comment '触发时间',
primary key(`id`),
key `type`(`type`)
)engine=innodb charset=utf8;

--用户拥有的优惠券表
create table `cake_user_coupon`(
`id` int auto_increment,
`coupon_id` int(11) not null comment '优惠券id',
`user_id` int(11) not null comment '用户id',
`type` tinyint(1) not null default 1 comment '用户拥有的优惠券状态  1 ： 可用  2  :  已使用   3  ： 冻结',
`create_time` int(11) not null comment '领取时间',
`pay_time` int(11) comment '支付时间',
`use_time` int(11) comment '使用时间',
`freeze_time` int(11) comment '冻结时间',
primary key(`id`),
key `type`(`type`)
)engine=innodb charset=utf8;

--购物车表
create table `cake_cart`(
`id` int(11) auto_increment,
`user_id` int(11) not null comment '用户id',
`goods_id` int(11) not null comment '商品id',
`sku_id` text not null comment 'sku组合 ids 如果为0则没有选择规格',
`goods_num` int(11) not null comment '商品数量',
`is_select` tinyint(1) not null default 1 comment '是否选中 0 否 1 是 默认选中',
`create_time` int(11) not null comment '商品添加时间',
primary key(`id`)
)engine=innodb charset=utf8;

--订单表
create table `cake_order`(
`id` int(11) auto_increment,
`order_code` varchar(18) not null comment '订单编号',
`user_id` int(11) not null comment '用户id',
`all_price` decimal(10,2) not null comment '优惠前的价格',
`coupon_money` decimal(10,2) not null default 0 comment '优惠多少钱',
`coupon_all_price` decimal(10,2) not null comment '优惠后 订单总金额 这里是实际付款金额 但没有计算用户账号余额',
`status` tinyint(1) not null default 1 comment '支付状态 1 未支付  2 已支付  3 未发货  4  已发货  5 待评价  6  交易成功  7  订单过期',
`user_nick` varchar(50) comment '买家昵称',
`user_rank_name` varchar(30) not null default '普通会员' comment '用户等级名称',
`coupon_name` varchar(128) not null default '' comment '优惠券名称',
`coupon_id` int(11) comment '优惠券id',
`post_type` tinyint(1) not null comment '送货方式 1 包邮  2 自提',
`pick_up_status` tinyint(1) not null default 0 comment '自提 状态 如果不是自提 此字段没用 0 未完成  1 已完成 ',
`desc` text comment '备注',
`create_time` int(11) not null comment '下单时间',
`pay_time` int(11) comment '支付时间',
primary key(`id`)
)engine=innodb charset=utf8;

--订单商品表
create table `cake_order_goods`(
`id` int(11) auto_increment,
`order_id` int(11) not null comment '订单id',
`goods_id` int(11) not null comment '商品id',
`goods_name` varchar(255) not null comment '商品名称',
`goods_thumb_img` varchar(256) not null comment '商品封面',
`goods_type` tinyint(1) not null comment '0  未开启属性选择   1   开启属性选择',
`goods_num` int(10) not null comment '商品数量',
`goods_sku_id` int(11) not null default 0 comment '商品sku的id',
`sku_group` varchar(999) comment '商品sku组合 数据的 json 没有则为空',
`goods_price` decimal(10,2) not null comment '商品售价(单价)',
`goods_rank_price` decimal(10,2) not null comment '商品会员售价(单价)',
primary key(`id`),
key `order_id`(`order_id`)
)engine=innodb charset=utf8;

--订单配送表
create table `cake_order_shipping`(
`id` int(11) auto_increment,
`order_id` int(11) not null comment '订单id',
`province` char(50) comment '省份',
`city` char(50) not null comment '城市',
`region` char(50) not null comment '区域',
`desc` varchar(256) not null comment '详细信息',
`user_name` char(50) not null comment '联系人姓名',
`user_phone` char(11) not null comment '联系人电话',
`user_telephone` char(20) comment '座机电话 选填',
primary key(`id`),
key `order_id`(`order_id`)
)engine=innodb charset=utf8;

--现金卡表
create table `cake_card`(
`id` int auto_increment,
`card_name` varchar(50) not null comment '名字',
`card_money` decimal(10,2) not null comment '客户入账 金额',
`card_price` decimal(10,2) not null comment '售价 金额 如果赠礼此字段作废',
`card_all_num` int(10) not null default 0 comment '发放总数',
`card_num` int(10) not null default 0 comment '库存',
`is_give` tinyint(1) not null comment '是否赠礼 如果赠礼 则用户无法购买 0 否 1 是',
`create_time` int(11) not null comment '创建时间',
`put_time` int(11) not null comment '发放时间',
`put_end_time` int(11) unsigned not null default 4102415999 comment '发放结束时间',
`start_time` int(11) not null comment '使用开始时间',
`end_time` int(11) unsigned not null default 4102415999 comment '使用到期时间 默认为永久',
primary key(`id`)
)engine=innodb charset=utf8;

--用户拥有现金卡表  核实 用此表
create table `cake_user_card`(
`id` int auto_increment,
`user_id` int(11) not null comment '用户id',
`card_id` int(11) not null comment '现金卡表id',
`card_code` varchar(18) not null comment '卡编号',
`status` tinyint(1) not null default 1 comment '当前状态 1 未使用 2 已使用 3 已被别人使用 4 系统收回',
`use_user_id` int(11) not null default 0 comment '使用者id 默认没有',
`from_type` tinyint(1) not null comment '该现金卡来源 1 购买 2 系统赠礼 3 他人给予现金卡码兑换',
`is_see` tinyint(1) not null default 0 comment '用户是否已经查看 0 否 1 是',
`card_info` varchar(999) not null comment '当时获取的现金卡 json信息',
`create_time` int(11) not null comment '创建日期',
`get_time` int(11) comment '领取日期',
`use_time` int(11) comment '使用日期',
primary key(`id`),
key `is_see`(`is_see`),
key `status`(`status`)
)engine=innodb charset=utf8;

--现金卡订单表
create table `cake_card_order`(
`id` int auto_increment,
`order_code` varchar(20) not null comment '订单号',
`user_id` int(11) not null comment '用户id',
`card_id` int(11) not null comment '购买的现金卡 id',
`money` decimal(10,2) not null comment '消费金额',
`is_pay` tinyint(1) not null default 0 comment '是否付款',
`create_time` int(11) not null comment '创建时间',
`pay_time` int(11) comment '付款时间',
primary key(`id`)
)engine=innodb charset=utf8;


select a.*,b.attr_value from cake_category_attr as a inner join cake_category_attr_val as b on a.id = b.attr_id where a.id = 5;