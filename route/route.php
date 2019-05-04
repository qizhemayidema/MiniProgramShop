<?php

Route::group('/admin', function () {
    Route::get('login', 'admin/Login/index');     //登录
    Route::post('login/check', 'admin/Login/check');   //登录验证
    Route::get('category/index', 'admin/Category/index');    //分类列表
    Route::get('/', 'admin/Index/index');            //主框架
});

//接口调用
Route::group('/api', function () {
    Route::post('/login', 'api/Login/login');            //登录接口
    Route::post('/onLoad','api/Index/onLoad');          //程序初始化

    //写数据的接口
    Route::group('/set', function () {
            Route::post('/cart','api/Cart/setCart');       //购物车添加数据
            Route::post('/cartUp','api/Cart/setCartUp');   //购物车某个商品+1数量
            Route::post('/cartDown','api/Cart/setCartDown');//购物车某个商品-1数量
            Route::post('/cartSelectOn','api/Cart/setCartSelectOn');    //购物车某个商品选中
            Route::post('/cartSelectOff','api/Cart/setCartSelectOff');    //购物车某个商品不选中
            Route::post('/cartDel','api/Cart/setCartDel');  //购物车删除某个用户的某个商品
            Route::post('/userCoupon','api/Coupon/setUserCoupon');        //用户领取优惠券
            Route::post('/useMoneyCoupon','api/Coupon/setUseMoneyCoupon');//用户使用现金券 不是 现金卡
            Route::post('/userAddress','api/User/setUserAddress');          //用户新增一个地址
            Route::post('/userAddressChange','api/User/setUserAddressChange');  //改变用户某个收货地址
            Route::post('/orderStatusOk','api/Order/setOrderStatusOk');     //改变用户某个订单为已发货的状态到6 交易成功
            Route::post('/delUserAddress','api/User/setDelUserAddress');    //删除某个收货地址
            Route::post('/userAddressDefault','api/User/setUserAddressDefault');    //设置某个用户的某个地址收货地址选中默认
            Route::post('/orderRemove','api/Order/setOrderRemove');         //用户取消未支付的订单
            Route::post('/useCard','api/Card/useCardCode');                 //用户使用现金卡接口
    });

    //获取数据的接口
    Route::group('get', function () {
        Route::post('/rollGoods', 'api/Goods/getRollGoods');     //获取轮播商品
        Route::post('/newGoods', 'api/Goods/getNewGoods');          //获取最新商品
        Route::post('/firstCate', 'api/Category/getCateOne');        //获取所有一级分类
        Route::post('/secCate', 'api/Category/getCateTwo');          //获取所有二级分类
        Route::post('/cateGoods', 'api/Goods/getCateGoods');         //获取分类下商品 列表
        Route::post('/goods', 'api/Goods/getGoodsInfo');             //获取某个商品详细信息
        Route::post('/cart','api/Cart/getCart');                     //获取某个用户的购物车
        Route::post('/secCateVal','api/Category/getCateTwoAttr');    //获取二级分类下的属性 及 属性值
        Route::post('/attrValGoods','api/Goods/getAttrValGoods');    //筛选规格返回商品
        Route::post('/coupon','api/Coupon/getCouponAll');            //获取所有优惠券
        Route::post('/newUserCoupon','api/Coupon/checkNewUser');      //检查是否存在新用户领取的优惠券
        Route::post('/userAddress','api/User/getUserAddress');        //获取用户收货地址列表
        Route::post('/userTypeCoupon','api/Coupon/getUserTypeCoupon');  //获取某个用户可用or已用优惠券
        Route::post('/bill','api/Cart/bill');                         //用户下单页面
        Route::post('/userInfo','api/User/getUser');                //获取用户基本信息
        Route::post('/order','api/Order/getOrder');                     //获取某个用户某个状态的订单
        Route::post('/userOrderCoupon','api/Coupon/getUserOrderCoupon'); //获取用户下单页面可用优惠券
        Route::post('/userAddressOne','api/user/getUserAddressOne');     //获取某个收货地址详细信息
        Route::post('/couponUser','api/Coupon/getCouponUser');                  //获取某个用户所有可用优惠券
        Route::post('/couponUserTimeOut','api/Coupon/getCouponUserTimeOut');    //获取某个用户的所有过期优惠券
        Route::post('/couponUserUseOut','api/Coupon/getCouponUserUseOut');      //获取某个用户的所有已用优惠券
        Route::post('/couponUserGetCoupon','api/Coupon/getUserGetCoupon');      //获取某个用户的所有可领优惠券
        Route::post('/orderInfo','api/Order/getOrderInfo');             //获取某个订单详细信息
        Route::post('/screenVal','api/Category/getScreenVal');          //获取某个二级分类下的筛选规格
        Route::post('/cardList','api/Card/getCardList');                //获取当前可买现金卡
        Route::post('/userCard','api/Card/getUserCard');                //获取用户所拥有的现金卡
    });
    //支付接口
    Route::group('pay',function(){
        Route::post('/card','api/Pay/cardPay');    //购买现金卡入口
        Route::post('/orderListPay','api/Pay/orderListPay');    //订单列表内 拉起支付
        Route::post('/notify', 'api/Pay/notify');     //回调接口
        Route::post('/', 'api/Pay/shop');    //支付商品入口
    });
});