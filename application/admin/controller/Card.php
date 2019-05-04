<?php

namespace app\admin\controller;

use think\Exception;
use think\Request;
use think\Validate;

use page\Page;

use app\admin\model\Card as CardModel;
use app\admin\model\UserCard as UserCardModel;
use app\admin\model\User as UserModel;

class Card extends Base
{
    protected $user_list_length = 3;    //用户列表分页
    protected $js_name = 'card_user_list_page';            //用户列表分页函数名
    //现金卡列表
    public function index()
    {
        $card_model = new CardModel();

        $card_info = $card_model->order('id','desc')->paginate(20);

        $card_count = $card_model->count('id');
        $this->assign('card_info',$card_info);
        $this->assign('card_count',$card_count);
        return $this->fetch();
    }

    //新增现金卡
    public function add(Request $request)
    {
        $user_model = new UserModel();
        $user_list_count = $user_model->count('id');
        if ($request->isGet()){
            //查询用户列表信息
            $user_info = $user_model->order('id','desc')->field('id,openid,nick_name,avatar_url')->limit($this->user_list_length)->select();
            if (count($user_info) < $this->user_list_length){
                $page_info = '';
            }else{
                //分页信息
                $page_info = (new Page($user_list_count,$this->user_list_length,$this->js_name,1))->render;
            }
            $this->assign('page_info',$page_info);
            $this->assign('user_info',$user_info);
            return $this->fetch();
        }elseif($request->isAjax()){ //查询用户信息
            $page = $request->param('page');
            $start_page = $this->user_list_length * $page - $this->user_list_length;

            //查询用户列表信息
            $user_info = $user_model->order('id','desc')->field('id,openid,nick_name,avatar_url')->limit($start_page,$this->user_list_length)->select();
            //分页信息
            $page_info = (new Page($user_list_count,$this->user_list_length,$this->js_name,$page))->render;

            $this->assign('user_info',$user_info);
            $this->assign('page_info',$page_info);

            return $this->fetch('add_user_ajax');
        }
    }

    //新增现金卡动作
    public function addChange(Request $request)
    {
        $rule = [
            'card_name'     => 'require',
            'card_money'    => 'require|number',
            'start_time'    => 'require',
            'is_give'       => 'require',
        ];

        $message = [
            'card_name.reuqire'     => '请填写名称',
            'card_money.require'    => '请填写用户入账金额',
            'card_money.number'     => '用户入账金额必须为数字',
            'start_time.require'    => '使用开始时间必须填写',
            'is_give.require'       => '非法操作'
        ];

        $data = $request->post();
//        return json($data);

        $validate = new Validate($rule,$message);

        $card_model = new CardModel();
        $user_card_model = new UserCardModel();

        if (!$validate->check($data)){
            return json(['code'=>0,'msg'=>$validate->getError()]);
        }

        //截止日期
        if ($data['end_time'] == ''){
            unset($data['end_time']);       //数据库 有默认
        }else{
            $data['end_time'] = strtotime($data['end_time']);
        }
        $data['start_time'] = strtotime($data['start_time']);

        unset($data['user_give_ids']);
        //验证以及处理数据 如果没有选择赠礼
        if ($data['is_give'] == '0'){

            //判断字段是否书写完整
            if (!is_numeric($data['card_num']) || $data['card_num'] < 0) return json(['code'=>0,'msg'=>'发放数量填写非法']);
            if (!is_numeric($data['card_price']) || $data['card_price'] < 0) return json(['code'=>0,'msg'=>'售价金额填写非法']);
            if ($data['put_time'] == '') return json(['code'=>0,'msg'=>'请填写发放开始日期']);

            if ($data['put_end_time'] == ''){
                unset($data['put_end_time']);
            }else{
                $data['put_end_time'] = strtotime($data['put_end_time']);
            }
            $data['put_time'] = strtotime($data['put_time']);
            $data['card_all_num'] = $data['card_num'];
            unset($data['user_give_num']);
        }else{
            //如果选择了赠礼
            if (!isset($data['user_give_num'])){
                return json(['code'=>0,'msg'=>'如果选择赠礼，请选择赠送对象']);
            }
            $data['card_num'] = 0;      //库存
            $data['card_all_num'] = 0;  //发布总量
            $data['card_price'] = 0;
            $data['put_time'] = 0;
            $data['put_end_time'] = 0;

            $user_give_num = $data['user_give_num'];
            unset($data['user_give_num']);
            //计算总库存
            foreach ($user_give_num as $key => $value){     //key 为用户id  value 为赠送几张券
                $data['card_all_num'] += $value;
            }

        }
        $data['create_time'] = time();

        $card_model->startTrans();
        try{
            $card_model->insert($data);

            $card_id = $card_model->getLastInsID();

            if ($data['is_give'] == '1'){       //如果是赠送的
                $data_json = json_encode($data,256);
                //循环组合数据
                $user_card_data = [];
                foreach ($user_give_num as $key => $value) {    //key 为用户id  value 为赠送几张券
                    for ($i = 0;$i < $value;$i++){
                        $user_card_data[] = [
                            'user_id'   => $key,
                            'card_id'   => $card_id,
                            'card_code' => strtoupper(mb_substr(md5($data_json.$key.$i.microtime()),0,18)),
                            'status'    => 1,
                            'from_type' => 2,
                            'is_see'    => 0,
                            'card_info' => $data_json,
                            'create_time' => time(),
                        ];
                    }
                }

                //一次性插入
                $user_card_model->insertAll($user_card_data);
            }
            $card_model->commit();
        }catch (Exception $e){
            $card_model->rollback();
            return json(['code'=>0,'msg'=>$e->getMessage()]);
        }

        return json(['code'=>1,'msg'=>'新增成功']);
    }

    //查看某个现金卡下的用户情况
    public function seeCardUser(Request $request)
    {
        $card_id = $request->param('card_id');

        $user_card_model = new UserCardModel();

        $data = $user_card_model->alias('user_card')
                        ->join('cake_user user_2','user_2.id = user_card.use_user_id','left')
                        ->join('cake_user user','user_card.user_id = user.id')
                        ->where(['user_card.card_id'=>$card_id])
                        ->order('user_card.create_time','desc')
                        ->field('user_card.id user_card_id,user.nick_name,user_2.nick_name use_user_nick_name,user_card.status,user_card.from_type,user_card.get_time,user_card.use_time,user_card.card_code,user_card.create_time')
                        ->paginate(20);
        $this->assign('user_card',$data);
        return $this->fetch('card/see_card_user');
    }

    //系统回收某个用户的现金卡
    public function reCard(Request $request)
    {
        $user_card_model = new UserCardModel();

        $user_card_id = $request->param('user_card_id');
        if (!$user_card_id){
            return json(['code'=>0,'msg'=>'操作非法']);
        }
        //判断此用户持有的优惠券状态是否为未使用 如果不是 则false
        $status = $user_card_model->where(['id'=>$user_card_id])->value('status');
        if ($status == 1){
            //此处改变状态
            $user_card_model->where(['id'=>$user_card_id])->update(['status'=>4]);
        }else{
            return json(['code'=>0,'msg'=>'用户非未使用状态，无法回收']);
        }

        return json(['code'=>1,'msg'=>'回收成功']);


    }
}
