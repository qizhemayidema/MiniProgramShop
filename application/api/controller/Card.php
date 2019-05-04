<?php

namespace app\api\controller;

use app\api\model\UserCard as UserCardModel;
use app\api\model\User as UserModel;
use app\api\model\Card as CardModel;

use think\Exception;
use think\Request;

use think\Validate;

class Card extends Base
{

    //获取现金券列表
    public function getCardList()
    {
        $card_model = new CardModel();
        $card_info = $card_model->where(['is_give'=>0])->where('card_num','>',0)->where('put_time','<',time())->where('put_end_time','>',time())->field('id,card_name,card_money,card_price,card_num,is_give,put_end_time,start_time,end_time')->select();
        return json(['code'=>1,'data'=>$card_info]);
    }

    /*
     * 购买 与购买后回调接口在pay控制器中
     * */
    //用户使用现金卡接口     （核销）
    public function useCardCode(Request $request)
    {
        $rule = [
            'token'       => 'require',
            'card_code'     => 'require',
        ];

        $message = [
            'token.require'       => 'error',
            'card_code.require'     => '操作非法2',
        ];
        $data = $request->post();
        $data['user_id'] = $this->getUserInfo($data['token']);

        $user_card_model = new UserCardModel();
        $user_model = new UserModel();

        $validate = new Validate($rule,$message);
        if (!$validate->check($data)){
            return json(['code'=>0,'data'=>$validate->getError()]);
        }
        //核销
        $user_card_info = $user_card_model->where(['card_code'=>$data['card_code'],'status'=>1])->find();
        $card_info = json_decode($user_card_info['card_info'],true);
        $user_card_model->startTrans();
        try{
            if ($user_card_info){       //证明此卡可用
                //判断是否用户自己使用
                if ($user_card_info['user_id'] == $data['user_id']){    //是本人使用 直接修改数据即可
                    $user_card_model->where(['id'=>$user_card_info['id']])->update([
                        'status'        => 2,
                        'use_user_id'   => $data['user_id'],
                        'use_time'      => time(),
                    ]);
                }else{      //不是本人使用 将修改之前的表数据 再新增一条数据
                    $user_card_model->where(['id'=>$user_card_info['id']])->update([
                        'status'        => 3,
                        'use_user_id'   => $data['user_id'],
                    ]);
                    $user_card_model->insert([
                        'user_id'   => $data['user_id'],
                        'card_id'   => $user_card_info['card_id'],
                        'card_code' => $user_card_info['card_code'],
                        'status'    => 2,
                        'use_user_id' => $data['user_id'],
                        'from_type' => 3,
                        'is_see'    => 1,
                        'card_info' => $user_card_info['card_info'],
                        'create_time' => time(),
                        'get_time' => time(),
                        'use_time' => time(),
                    ]);
                }
            }else{
                throw new Exception('此卡无效');
            }
            //为使用者添加金额
            $user_model->where(['id'=>$data['user_id']])->setInc('money',$card_info['card_money']);

            $user_card_model->commit();
        }catch (Exception $e){
            $user_card_model->rollback();
            return json(['code'=>0,'data'=>$e->getMessage()]);
        }

        return json(['code'=>1,'data'=>'兑换成功！']);
    }

    public function getUserCard(Request $request)
    {
        $token = $request->post('token');
        if (!$token) {
            return json(['code' => 0, 'data' => '操作非法']);
        }
        $user_id = $this->getUserInfo($token);
        $user_card_model = new UserCardModel();
        $data = $user_card_model->alias('user_card')
                        ->join('cake_card card','user_card.card_id = card.id')
                        ->where(['user_card.user_id'=>$user_id])
                        ->where('card.start_time','<',time())
                        ->where('card.end_time','>',time())
                        ->where(['user_card.status'=>1])
                        ->field('card.*,user_card.card_code')
                        ->select();

        return json(['code'=>1,'data'=>$data]);
    }
}
