<?php

namespace app\api\controller;

use think\Controller;
use think\Request;
use think\Validate;

use app\api\model\User as UserModel;
use app\api\model\UserAddress as UserAddressModel;

class User extends Base
{
    //获取用户信息
    public function getUser(Request $request)
    {
        $token = $request->param('token');
        if (!$token) {
            return json(['code' => 0, 'data' => 'error']);
        }
        $user_id = $this->getUserInfo($token);
        $user_model = new UserModel();

        $user_info = $user_model->where(['id' => $user_id])->field('nick_name,id,avatar_url,all_score,money')->find();

        return json(['code' => 1, 'data' => $user_info]);
    }

    //获取用户的收货地址
    public function getUserAddress(Request $request)
    {
        $rule = [
            'token' => 'require',
        ];

        $message = [
            'token.require' => 'error',
        ];
        $data = $request->param();
        $data['user_id'] = $this->getUserInfo($data['token']);

        $user_address_model = new UserAddressModel();
        $validate = new validate($rule, $message);
        if (!$validate->check($data)) {
            return json(['code' => 0, 'data' => $validate->getError()]);
        }

        $res = $user_address_model->where(['user_id' => $data['user_id']])->select();

        return json(['code' => 1, 'data' => $res]);
    }

    //新增某个用户的收货地址
    public function setUserAddress(Request $request)
    {
        $rule = [
            'token' => 'require',
            'province' => 'require',
            'city' => 'require',
            'region' => 'require',
            'desc' => 'require|max:80',
            'user_name' => 'require',
            'user_phone' => 'mobile',
        ];

        $message = [
            'token.require' => 'error',
            'province.require' => '请求请携带province',
            'city.require' => '请求请携带city',
            'region.require' => '请求请携带region',
            'desc.require' => '详细信息必须填写',
            'desc.max' => '超出详细信息最大限度',
            'user_name.require' => '联系人名称必须填写',
            'user_phone.mobile' => '联系人电话不符合格式',
        ];
        $data = $request->post();
        $data['user_id'] = $this->getUserInfo($data['token']);
        $address_model = new UserAddressModel();
        $validate = new Validate($rule, $message);
        if (!$validate->check($data)) {
            return json(['code' => 0, 'data' => $validate->getError()]);
        }
        unset($data['token']);
        if (!$data['user_phone'] && !$data['user_telephone']) {
            return json(['code' => 0, 'data' => '联系人电话至少必须填写一个']);
        }

        //判断是否存在其他默认收货地址
        if (isset($data['is_select']) && $data['is_select'] == 1) {
            $address_model->where(['user_id' => $data['user_id']])->update(['is_select' => 0]);
        } else {
            if (!$address_model->where(['user_id' => $data['user_id'], 'is_select' => '1'])->find()) {
                $data['is_select'] = 1;
            } else {
                $data['is_select'] = 0;
            }
        }
        if (isset($data['user_telephone'])) {
            $data['user_telephone'] = htmlentities($data['user_telephone']);
        }
        $data['desc'] = htmlentities($data['desc']);
        $data['user_name'] = htmlentities($data['user_name']);
        $data['create_time'] = time();
        $address_model->insert($data);

        return json(['code' => 1, 'data' => 'success']);
    }

    //修改某个用户的收货地址
    public function setUserAddressChange(Request $request)
    {
        $rule = [
            'address_id' => 'require',
            'token' => 'require',
            'province' => 'require',
            'city' => 'require',
            'region' => 'require',
            'desc' => 'require|max:80',
            'user_name' => 'require',
            'user_phone' => 'mobile',
        ];

        $message = [
            'address_id.require' => '操作非法',
            'token.require' => 'error',
            'province.require' => '请求请携带province',
            'city.require' => '请求请携带city',
            'region.require' => '请求请携带region',
            'desc.require' => '详细信息必须填写',
            'desc.max' => '超出详细信息最大限度',
            'user_name.require' => '联系人名称必须填写',
            'user_phone.mobile' => '联系人电话不符合格式',
        ];

        $data = $request->post();
        $address_model = new UserAddressModel();
        $validate = new Validate($rule, $message);
        if (!$validate->check($data)) {
            return json(['code' => 0, 'data' => $validate->getError()]);
        }
        $data['user_id'] = $this->getUserInfo($data['token']);
        unset($data['token']);

        if (!$data['user_phone'] && !$data['user_telephone']) {
            return json(['code' => 0, 'data' => '联系人电话至少必须填写一个']);
        }

        //判断是否存在其他默认收货地址
        if (isset($data['is_select']) && $data['is_select'] == 1) {
            $address_model->where(['user_id' => $data['user_id']])->update(['is_select' => 0]);
        } else {
            if (!$address_model->where(['user_id' => $data['user_id'], 'is_select' => '1'])->where('id','<>',$data['address_id'])->find()) {
                $data['is_select'] = 1;
            } else {
                $data['is_select'] = 0;
            }
        }
        if (isset($data['user_telephone'])) {
            $data['user_telephone'] = htmlentities($data['user_telephone']);
        }
        $data['desc'] = htmlentities($data['desc']);
        $data['user_name'] = htmlentities($data['user_name']);
        $address_id = $data['address_id'];
        unset($data['address_id']);
        $address_model->where(['id' => $address_id])->update($data);

        return json(['code' => 1, 'data' => 'success']);

    }

    //删除某个用户的收货地址
    public function setDelUserAddress(Request $request)
    {
        $rule = [
            'address_id'    => 'require',
            'token'       => 'require',
        ];

        $message = [
            'address_id.require'    => '请携带address_id',
            'token.require'       => 'error',
        ];
        $data = $request->post();
        $data['user_id'] = $this->getUserInfo($data['token']);
        $validate = new Validate($rule,$message);
        if (!$validate->check($data)){
            return json(['code'=>0,'data'=>$validate->getError()]);
        }
        unset($data['token']);
        $address_model = new UserAddressModel();
        $address_model->where(['id'=>$data['address_id']])->delete();
        if ($address_model->where(['is_select'=>0,'user_id'=>$data['user_id']])->field('id')->find()){
            $address_model->where(['user_id'=>$data['user_id']])->order('create_time','desc')->limit(1)->update(['is_select'=>1]);
        }
        return json(['code'=>1,'data'=>'success']);
    }

    //获取某个收货地址详细信息
    public function getUserAddressOne(Request $request)
    {
        $address_id = $request->param('address_id');
        $token = $request->param('token');
        if (!$address_id) {
            return json(['code' => 0, 'data' => '请携带address_id']);
        }
        if (!$token) {
            return json(['code' => 0, 'data' => 'error']);
        }
        $user_id = $this->getUserInfo($token);
        $address_model = new UserAddressModel();
        $data = $address_model->where(['user_id'=>$user_id,'id'=>$address_id])->find();

        return json(['code'=>1,'data'=>$data]);
    }

    //修改用户默认选中地址
    public function setUserAddressDefault(Request $request)
    {
        $rule = [
            'token'       => 'require',
            'address_id'    => 'require',
        ];
        $message = [
            'token.require'   => 'error',
            'address_id.require'    => '请求非法',
        ];

        $data = $request->post();

        $data['user_id'] = $this->getUserInfo($data['token']);

        $user_address_model = new UserAddressModel();
        $validate = new Validate($rule,$message);
        if (!$validate->check($data)){
            return json(['code'=>0,'data'=>$validate->getError()]);
        }

        $user_address_model->where(['user_id'=>$data['user_id'],'is_select'=>1])->update(['is_select'=>0]);
        $user_address_model->where(['id'=>$data['address_id']])->update(['is_select'=>1]);

        return json(['code'=>1,'data'=>'success']);
    }
}

