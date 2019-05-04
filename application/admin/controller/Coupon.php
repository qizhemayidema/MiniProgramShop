<?php

namespace app\admin\controller;

use think\Controller;
use think\Request;
use app\admin\model\Coupon as CouponModel;
use app\admin\model\Goods as GoodsModel;

use app\admin\validate\CouponAdd as CouponValidate;

use think\Validate;

use page\Page;

class Coupon extends Base
{
    protected $cate_goods_list_row = 10;

    public function index()
    {
        $coupon_model = new CouponModel();
        $data = $coupon_model->where('end_time','>',time())->order('id','desc')->paginate(20);
        $this->assign('data',$data);
        return $this->fetch();
    }

    public function add(Request $request)
    {
        if ($request->isAjax()){
            $coupon_model = new CouponModel();
            $goods_model = new GoodsModel();
            $data = $request->post();
            if (!isset($data['page'])){
                $data['page'] = 1;
            }
            $res = $coupon_model->getCateGoods($data['cate_id'],$data['page'],$this->cate_goods_list_row);
            //数据总长度
            if ($data['cate_id'] != 0) {
                $count = $goods_model->where(['cate_id'=>$data['cate_id']])->where(['delete_time'=>0])->count();
            }else{
                $count = $goods_model->where(['delete_time'=>0])->count();
            }
            //分页类
            $page = new Page($count,$this->cate_goods_list_row,'show_goods_list',$data['page']);
            if ($count == 0){
                $page_html = '';
            }else{
                $page_html = $page->render;
            }
            $this->assign('page',$page_html);
            $this->assign('data',$res);
            $html = $this->fetch('coupon/cate_goods_ajax');
            return json(['data'=>$data,'html'=>$html]);
        }
        return $this->fetch();
    }

    public function addChange(Request $request)
    {
        $data = $request->post();
        $coupon_model = new CouponModel();
        $validate = new CouponValidate();
        if (!$validate->check($data)){
            return json(['code'=>0,'msg'=>$validate->getError()]);
        }
        if ($data['put_type'] != 2){        //如果发放类型不是需要用户领取的
            unset($data['count']);
        }else{
            if ($data['count'] == ''){
                return json(['code'=>0,'msg'=>'发放数量不能为空']);
            }else{
                if(!preg_match("/^[1-9][0-9]*$/",$data['count'])){
                    return json(['code'=>0,'msg'=>'发放数量必须为正整数']);
                }
            }
        }
        unset($data['goods_ids_checked']);
        //判断优惠力度
        if (!is_numeric($data['money'])){
            return json(['code'=>0,'msg'=>'优惠力度必须为数字']);
        }else{
            if ($data['money'] < 0){
                return json(['code'=>0,'msg'=>'优惠力度必须大于0']);
            }
        }
        //如果优惠券是满减券
        if ($data['type'] == 1){
            //判断满足金额
            if ($data['cond'] == ''){
                return json(['code'=>0,'msg'=>'满足金额必须填写']);
            }else{
                if (!is_numeric($data['cond'])){
                    return json(['code'=>0,'msg'=>'满足金额必须为数字']);
                }else{
                    if ($data['cond'] < 0){
                        return json(['code'=>0,'msg'=>'满足金额必须大于0']);
                    }
                }
            }
        }

        //如果优惠券是现金券
        if ($data['type'] == 3){
            unset($data['is_all']);
        }else{
            if ($data['is_all'] == 0){
                if ($data['goods_ids'] == ''){
                    return json(['code'=>0,'msg'=>'如果不能全场可用，请选择可用商品']);
                }
            }
        }

        if ($data['put_time'] > $data['put_end_time']){
            return json(['code'=>0,'msg'=>'发放开始日期不能在截止日期之后']);
        }
        if ($data['start_time'] > $data['end_time']){
            return json(['code'=>0,'msg'=>'使用开始日期不能在截止日期之后']);
        }

        $data['put_time'] = strtotime($data['put_time']);
        $data['put_end_time'] = strtotime($data['put_end_time']);
        $data['start_time'] = strtotime($data['start_time']);
        $data['end_time'] = strtotime($data['end_time']);

        //优惠券编号
        $data['code'] =date('Ymd') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);

        $data['create_time'] = time();
        $coupon_model->insert($data);
        return json(['code'=>1,'msg'=>'添加成功！']);
    }

    public function edit(Request $request)
    {
        if ($request->isAjax()){
            $coupon_model = new CouponModel();
            $goods_model = new GoodsModel();
            $data = $request->post();
            if (!isset($data['page'])){
                $data['page'] = 1;
            }
            $goods_ids = $coupon_model->field('goods_ids')->find($data['coupon_id']);

            $start_page = $data['page'] * $this->cate_goods_list_row - $this->cate_goods_list_row;
            $res = $goods_model->field('id,name,thumb_img')->whereIn('id',$goods_ids['goods_ids'])->limit($start_page,$this->cate_goods_list_row)->select();
            //分页类
            $count = count(explode(',',$goods_ids['goods_ids']));
            $page = new Page($count,$this->cate_goods_list_row,'show_goods_list',$data['page']);
            if ($count == 0){
                $page_html = '';
            }else{
                $page_html = $page->render;
            }
            $this->assign('page',$page_html);
            $this->assign('data',$res);
            $html = $this->fetch('coupon/edit_coupon_goods_ajax');
            return json(['data'=>$data,'html'=>$html]);
        }
        $coupon_id = $request->param('id');
        $coupon_model = new CouponModel();
        $data = $coupon_model->where(['id'=>$coupon_id])->find();
        $this->assign('data',$data);
        return $this->fetch();
    }

    public function editChange(Request $request)
    {
        $data = $request->post();
        $rule = [
            'id'        => 'require',
            'end_time'  => 'require',
            'put_end_time'  => 'require',
        ];

        $message = [
            'id.require'    => '请求非法',
            'put_end_time.require'  => '发放日期必须填写',
            'end_time.require'  => '使用截止日期必须填写',
        ];

        $coupon_model = new CouponModel();
        $validate = new Validate($rule,$message);
        if (!$validate->check($data)){
            return json(['code'=>0,'msg'=>$validate->getError()]);
        }
        $old_data = $coupon_model->field('start_time,put_time')->find($data['id']);
        $data['put_end_time'] = strtotime($data['put_end_time']);
        $data['end_time'] = strtotime($data['end_time']);
        if ($old_data['put_time'] > $data['put_end_time']){
            return json(['code'=>0,'msg'=>'发放开始日期不能在截止日期之后']);
        }
        if ($old_data['start_time'] > $data['end_time']){
            return json(['code'=>0,'msg'=>'使用开始日期不能在截止日期之后']);
        }
        $coupon_model->where(['id'=>$data['id']])->update($data);
        return json(['code'=>1,'msg'=>'修改成功']);
    }
}