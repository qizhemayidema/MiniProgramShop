<?php

namespace app\admin\controller;

use think\Exception;
use think\Request;
use app\admin\model\Category as CateModel;
use app\admin\model\CategoryAttr as CategoryAttrModel;
use app\admin\model\CategoryAttrVal as AttrValModel;
use app\admin\model\Goods as GoodsModel;
use app\admin\model\GoodsUserRanksPrice as RankPriceModel;
use app\admin\model\UserRanks as RankModel;
use app\admin\model\GoodsSku as GoodsSkuModel;
use app\admin\model\GoodsSkuUserRanksPrice as SkuRankPriceModel;
use app\admin\model\CategoryScreen as CateScreenModel;
use app\admin\model\CategoryScreenVal as CateScreenValModel;

use page\Page;

use think\Db;

use app\admin\validate\GoodsAdd as GoodsAddValidate;

//商品页面
class Goods extends Base
{
    protected $list_row = 20;   //每页显示数据长度

    public function index(Request $request)
    {
        $goods_model = new GoodsModel();
        if ($request->isAjax()) {
            $data = $request->param();
            $start_page = $data['page'] * $this->list_row - $this->list_row;
            $source = $goods_model->alias('goods')->join('category cate', 'cate.id = goods.cate_id')->where(['goods.delete_time' => 0]);
            if (isset($data['cate_id']) && $data['cate_id'] != 0) {
                $res = $source->where(['goods.cate_id' => $data['cate_id']])->order('goods.id', 'desc')->limit($start_page, $this->list_row)->field('goods.*,cate.name cate_name')->select();
                $count = $goods_model->where(['cate_id' => $data['cate_id']])->count();
            } else {
                $res = $source->order('goods.id', 'desc')->limit($start_page, $this->list_row)->field('goods.*,cate.name cate_name')->select();
                $count = $goods_model->count();
            }
            $page_obj = new Page($count, $this->list_row, 'goods_index_page', $data['page']);
            $this->assign('data', $res);
            $this->assign('count', $count);
            if ($count == 0) {
                $this->assign('page_html', '');
            } else {
                $this->assign('page_html', $page_obj->render);
            }
            $html = $this->fetch('goods/index_ajax');
            return json(['code' => 1, 'html' => $html, 'count' => $count]);
        } else {
            $count = $goods_model->count();
            $page_obj = new Page($count, $this->list_row, 'goods_index_page', '1');
            $data = $goods_model->alias('goods')->join('category cate', 'goods.cate_id = cate.id')->where(['goods.delete_time' => 0])->order('id', 'desc')->limit($this->list_row)->field('goods.*,cate.name cate_name')->select();
            $this->assign('data', $data);
            $this->assign('page_html', $page_obj->render);
            $this->assign('count', $count);
            return $this->fetch();
        }
    }

    public function add(Request $request)
    {
        $attr_model = new CategoryAttrModel();
        $cate_model = new CateModel();
        $rank_model = new RankModel();
        $cate_screen_model = new CateScreenModel();
        $cate_screen_val_model = new CateScreenValModel();
        $cate_id = $request->param('cate_id');
        $attr_temp = $attr_model->getCateAttrOne($cate_id);
        $cate = $cate_model->where(['id' => $cate_id])->find();
        $attr = [];
        foreach ($attr_temp as $key) {
            if ($key['type'] == '商品参数') {
                $attr['商品参数'][] = $key;
            } else {
                $attr['用户选择属性'][] = $key;
            }
        }
        //查询会员信息
        $rank_info = $rank_model->where(['delete_time' => 0])->field('id,rank_name')->select();

        //查询筛选规格信息
        $screen_info = $cate_screen_model->where(['delete_time' => 0, 'cate_id' => $cate_id])->field('id,screen_name')->select()->toArray();
        $screen_ids = array_column($screen_info, 'id');
        $screen_val_info = $cate_screen_val_model->whereIn('screen_id', $screen_ids)->select()->toArray();
        foreach ($screen_info as &$key) {
            foreach ($screen_val_info as $key1) {
                if ($key1['screen_id'] == $key['id']) {
                    $key['val'][] = $key1;
                }
            }
        }


        $this->assign('rank_info', $rank_info);
        $this->assign('cate', $cate);
        $this->assign('attr', $attr);
        $this->assign('screen_info', $screen_info);
        return $this->fetch();
    }

    public function addChange(Request $request)
    {
        $data = $request->post();
        $validate = new GoodsAddValidate();
        $attr_val_model = new AttrValModel();
        $attr_model = new CategoryAttrModel();
        $goods_model = new GoodsModel();
        $rank_price_model = new RankPriceModel();
        $sku_model = new GoodsSkuModel();
        $sku_rank_price_model = new SkuRankPriceModel();
//
        if (!$validate->check($data)) {
            return json(['code' => 0, 'msg' => $validate->getError()]);
        }
        if ($data['is_roll'] == 1 && $data['roll_pic'] == '') {
            return json(['code' => 0, 'msg' => '如果选择轮播，则必须上传轮播图']);
        }

        //判断是否开启选择属性     如果没有开启  判断库存是否填写
        if ($data['is_attr'] == 0) {
            if (!is_numeric($data['num']) || $data['num'] < 0) {
                return json(['code' => 1, 'msg' => '如果不开启属性选择，请填写商品数量，商品数量必须大于等于0']);
            }
            if (!is_numeric($data['price']) || $data['price'] < 0) {
                return json(['code' => 1, 'msg' => '如果不开启属性选择，请填写商品售价，商品数量必须大于等于0']);
            }
            $data['show_price'] = $data['price'];
        } else {      //如果开启 则 商品表库存为0      售价为0      判断显示售价是否合法
            $data['num'] = 0;
            $data['price'] = 0;
            if (!isset($data['sku_group'])) {
                return json(['code' => 0, 'msg' => '如果开启属性选择 则必须至少选择一种属性']);
            }
            if ($data['show_price'] == '') {
                return json(['code' => 0, 'msg' => '如果开启属性选择，请填写显示售价 例如 99 ~ 198']);
            }
            //验证数据的完整性  sku_group是否填写齐全
            foreach ($data['sku_group'] as $key => $value) {
                if ($value['goods_num'] == '' || $value['goods_price'] == '') {
                    return json(['code' => 0, 'msg' => 'sku库存及价格必须填写齐全']);
                }
                if (isset($value['rank_price'])) {
                    foreach ($value['rank_price'] as $key1 => $value1) {
                        if (!is_numeric($value1) || $value1 < 0) {
                            return json(['code' => 0, 'msg' => 'sku的会员价格必须大于0']);
                        }
                    }
                }
            }
            $sku_group = $data['sku_group'];
            unset($data['sku_group']);
        }
        //拿出sku组合数据
        unset($data['file']);
        $data['create_time'] = time();

        //根据$data['attr_val_ids']下的key查询出所有属性
        $attr_select_ids = [];     //所选属性id
        if (isset($data['attr_val_ids'])) {
            foreach ($data['attr_val_ids'] as $key => $value) {
                if ($value[0] !== '') {
                    $attr_select_ids[] = $key;
                }
            }
        }

        //查出所有为文本框的属性
        $input_attr = $attr_model->whereIn('id', $attr_select_ids)->where(['edit_type' => 3])->select();

        //查询 属性表 所有数据
        $attrs = Db::table('cake_category_attr')->where(['delete_time' => 0])->where('cate_id', $data['cate_id'])->select();     //查出所有分类下的属性
        $data['goods_attr'] = json_encode($attrs, JSON_UNESCAPED_UNICODE);

        $attr_ids = [];
        //获取所有属性id
        foreach ($attrs as $key) {
            $attr_ids[] = $key['id'];
        }
//        return json($data);
        //查询所有属性值
        $all_attr_val = $attr_val_model->whereIn('attr_id', $attr_ids)->select()->toArray();

        $input_ids = [];        //文本框类型的属性值表ids     下标为属性id  value为属性值id
        foreach ($all_attr_val as &$key) {
            foreach ($input_attr as $key1) {
                if ($key['attr_id'] == $key1['id']) {
                    $input_ids[$key1['id']] = $key['id'];
                    $key['attr_value'] = $data['attr_val_ids'][$key1['id']][0];
                }
            }
        }
        if (isset($key)) unset($key);

        foreach ($input_ids as $key => $value) {
            $data['attr_val_ids'][$key][0] = $value;
        }

        //生成所有选中属性值的ids
        $temp = [];
        if (isset($data['attr_val_ids'])) {
            foreach ($data['attr_val_ids'] as $key) {
                foreach ($key as $key11 => $value) {
                    if ($value !== '') {
                        $temp[] = $value;
                    }
                }
            }
        }

        //$all_attr_val 是合成后所有属性值  用于 goods_attr字段
        $data['goods_attr_val'] = json_encode($all_attr_val, JSON_UNESCAPED_UNICODE);
        //所选中的属性值
        if (!empty($attr_select_ids)) {
            unset($data['attr_val_ids']);
            foreach ($all_attr_val as $key) {
                if (in_array($key['id'], $temp) && $key['attr_value'] !== '') {
                    $data['attr_val'][] = $key['attr_value'];
                    $data['attr_val_ids'][] = $key['id'];
                }
            }
            $data['attr_val'] = implode(',', $data['attr_val']);
            $data['attr_val_ids'] = implode(',', $data['attr_val_ids']);
        } else {
            $data['attr_val_ids'] = '';
            $data['attr_val'] = '';
        }

        //会员价格取出
        if (isset($data['rank_price'])) {
            $rank_price = $data['rank_price'];
            unset($data['rank_price']);
        }
        //处理筛选规格
        if (isset($data['screen_val_ids']) && !empty($data['screen_val_ids'])) {
            $data['screen_val_ids'] = implode(',', $data['screen_val_ids']);
        } else {
            $data['screen_val_ids'] = '';
        }

        $goods_model->startTrans();
        try {
            $res = $goods_model->insert($data);
            if (!$res) {
                throw new Exception('操作失误，请刷新后重新尝试', 1004);
            }
            //商品id
            $goods_id = $goods_model->getLastInsID();

            //如果sku组合存在
            if (isset($sku_group)) {
                //sku组合入库
                foreach ($sku_group as $key => $value) {     //$key 为 组合成的id     value 分 goods_num  goods_price   rank_price
                    $temp = [
                        'goods_id' => $goods_id,
                        'sku_group' => $key,
                        'goods_num' => $value['goods_num'],
                        'goods_price' => $value['goods_price'],
                        'create_time' => time(),
                    ];
                    $res = $sku_model->insert($temp);
                    if (!$res) {
                        throw new Exception('操作失误，请刷新后重新尝试', 1004);
                    }
                    $sku_id = $sku_model->getLastInsID();
                    //sku 会员价格入库
                    if (isset($value['rank_price'])) {
                        foreach ($value['rank_price'] as $key => $value) {   //此处 会员售价  key 为 会员id  value 为 售价
                            $sku_rank_price = [
                                'goods_sku_id' => $sku_id,
                                'rank_id' => $key,
                                'goods_id' => $goods_id,
                                'price' => $value,
                                'create_time' => time(),
                            ];
                            $res = $sku_rank_price_model->insert($sku_rank_price);
                            if (!$res) {
                                throw new Exception('操作失误，请刷新后重新尝试', 1004);
                            }
                        }
                    }
                }
            } else {      //如果不存在
                //会员价格入库
                if (isset($rank_price)) {
                    unset($temp);
                    $rank_price_data = [];
                    foreach ($rank_price as $key => $value) {
                        $temp['goods_id'] = $goods_id;
                        $temp['rank_id'] = $key;
                        $temp['create_time'] = time();
                        if ($value === '') {
                            $temp['price'] = $data['price'];
                        } else {
                            $temp['price'] = $value;
                        }
                        $rank_price_data[] = $temp;
                    }
                    $res = $rank_price_model->insertAll($rank_price_data);
                    if (!$res) {
                        throw new Exception('操作失误，请刷新后重新尝试', 1004);
                    }
                }
            }
            $goods_model->commit();
        } catch (Exception $e) {
            $goods_model->rollback();
            return json(['code' => 0, 'msg' => $e->getMessage()]);
        }
        return json(['code' => '1', 'msg' => '新增成功']);
    }

    public function edit(Request $request)
    {
        $goods_model = new GoodsModel();
        $attr_model = new CategoryAttrModel();
        $cate_model = new CateModel();
        $rank_model = new RankModel();
        $goods_sku_model = new GoodsSkuModel();
        $rank_price_model = new RankPriceModel();
        $cate_screen_model = new CateScreenModel();
        $cate_screen_val_model = new CateScreenValModel();


        $goods_id = $request->param('id');
        if (!$goods_id) die;

        //查询商品信息
        $data = $goods_model->find($goods_id);
        $goods_attr = json_decode($data['goods_attr'], true);
        $goods_attr_val = json_decode($data['goods_attr_val'], true);
        $data['screen_val_ids'] = explode(',', $data['screen_val_ids']);

        //属性拼成数组
        $temp_attr = $attr_model->goodsAttrMerge($goods_attr, $goods_attr_val);

        $attr = [];
        foreach ($temp_attr as $key) {
            if ($key['type'] == 1) {     //商品参数
                $attr['商品参数'][] = $key;
            } else {
                $attr['用户选择属性'][] = $key;
            }
        }

        $data['attr_val_ids'] = explode(',', $data['attr_val_ids']);
        $data['attr_val'] = explode(',', $data['attr_val']);

        //所属分类
        $cate = $cate_model->where(['id' => $data['cate_id']])->find();

        //如果开启选择属性了
        if ($data['is_attr'] == 1) {
            //查询sku 库存 售价
            //查询sku 会员售价
        } else {            //如果没有开启
            //查询会员价格数据

        }

        //查询会员列表
        $rank_list = $rank_model->field('id,rank_name')->select()->toArray();

        //查找会员数据与会员价格数据
        //查询此商品在cake_goods_user_ranks_price的数据
        $user_rank_price = $rank_price_model->where(['goods_id' => $data['id']])->select()->toArray();

        foreach ($user_rank_price as $key) {
            foreach ($rank_list as &$key1) {
                if ($key1['id'] == $key['rank_id']) {
                    $key1['rank_price'] = $key;
                }
            }
        }

        unset($key1);

        //查询筛选规格信息
        $screen_info = $cate_screen_model->where(['delete_time' => 0, 'cate_id' => $data['cate_id']])->field('id,screen_name')->select()->toArray();
        $screen_ids = array_column($screen_info, 'id');
        $screen_val_info = $cate_screen_val_model->whereIn('screen_id', $screen_ids)->select()->toArray();
        foreach ($screen_info as &$key) {
            foreach ($screen_val_info as $key1) {
                if ($key1['screen_id'] == $key['id']) {
                    $key['val'][] = $key1;
                }
            }
        }

        //详情图转数组
        $detail_img = explode(',', $data['detail_img']);
        $this->assign('detail_img', $detail_img);
        $this->assign('cate', $cate);
        $this->assign('rank_list', $rank_list);
        $this->assign('data', $data);
        $this->assign('attr', $attr);
        $this->assign('screen_info', $screen_info);

        return $this->fetch();
    }

    public function editChange(Request $request)
    {
        $data = $request->post();
        $validate = new GoodsAddValidate();
        $goods_model = new GoodsModel();
        $rank_price_model = new RankPriceModel();
        $sku_model = new GoodsSkuModel();
        $sku_rank_price_model = new SkuRankPriceModel();
        if (!isset($data['id'])) {
            return json(['code' => 0, 'msg' => '请求非法']);
        }
//
        if (!$validate->check($data)) {
            return json(['code' => 0, 'msg' => $validate->getError()]);
        }
        if ($data['is_roll'] == 1 && $data['roll_pic'] == '') {
            return json(['code' => 0, 'msg' => '如果选择轮播，则必须上传轮播图']);
        }

        //判断是否开启选择属性     如果没有开启  判断库存是否填写
        if ($data['is_attr'] == 0) {
            if (!is_numeric($data['num']) || $data['num'] < 0) {
                return json(['code' => 1, 'msg' => '如果不开启属性选择，请填写商品数量，商品数量必须大于等于0']);
            }
            if (!is_numeric($data['price']) || $data['price'] < 0) {
                return json(['code' => 1, 'msg' => '如果不开启属性选择，请填写商品售价，商品数量必须大于等于0']);
            }
            $data['show_price'] = $data['price'];
        } else {      //如果开启 则 商品表库存为0      售价为0      判断显示售价是否合法
            $data['num'] = 0;
            $data['price'] = 0;
            if (!isset($data['sku_group'])) {
                return json(['code' => 0, 'msg' => '如果开启属性选择 则必须至少选择一种属性']);
            }
            if ($data['show_price'] == '') {
                return json(['code' => 0, 'msg' => '如果开启属性选择，请填写显示售价 例如 99 ~ 198']);
            }
            //验证数据的完整性  sku_group是否填写齐全
            foreach ($data['sku_group'] as $key => $value) {
                if ($value['goods_num'] == '' || $value['goods_price'] == '') {
                    return json(['code' => 0, 'msg' => 'sku库存及价格必须填写齐全']);
                }
                if (isset($value['rank_price'])) {
                    foreach ($value['rank_price'] as $key1 => $value1) {
                        if (!is_numeric($value1) || $value1 < 0) {
                            return json(['code' => 0, 'msg' => 'sku的会员价格必须大于0']);
                        }
                    }
                }
            }
            $sku_group = $data['sku_group'];
            unset($data['sku_group']);
        }
        
        //拿出sku组合数据
        unset($data['file']);
        $data['create_time'] = time();
        //根据$data['attr_val_ids']下的key查询出所有属性
        $attr_select_ids = [];     //所选属性id
        if (isset($data['attr_val_ids'])) {
            foreach ($data['attr_val_ids'] as $key => $value) {
                if ($value[0] !== '') {
                    $attr_select_ids[] = $key;
                }
            }
        }
        $attrs = json_decode($data['goods_attr'], true);     //冗余的属性
        $data['goods_attr'] = json_decode($data['goods_attr'], true);     //冗余的属性

        $data['goods_attr_val'] = json_decode($data['goods_attr_val'], true);        //冗余的 属性值

        $input_ids = [];        //文本框类型的属性值表ids     下标为属性id  value为属性值id
        foreach ($data['goods_attr'] as $key => &$value) {
            if ($value['edit_type'] == 3) {
                $value['value'] = $data['attr_val_ids'][$value['id']][0];
                foreach ($data['goods_attr_val'] as $key1 => &$value1) {
                    if ($value1['attr_id'] == $value['id']) {
                        $value1['attr_value'] = $data['attr_val_ids'][$value['id']][0];
                        $input_ids[$value1['attr_id']] = $value1['id'];
                    }
                }
            }
        }

        if (isset($value)) unset($value);
        if (isset($value1)) unset($value1);

        //算出attr_val 和 attr_val_ids 选中的所有属性ids  和 选中的所有属性值
        $attr_val_ids = [];     // value 为选中属性值的id
        if (isset($data['attr_val_ids'])) {
            foreach ($data['attr_val_ids'] as $key => $value) {     //循环后变为 key为属性id  value为属性值id  包括文本id
                //判断此属性值是否为文本框
                if (isset($input_ids[$key])) {       //这个属性值是文本框
                    $attr_val_ids[$key] = $input_ids[$key];
                } else {
                    foreach ($value as $key1 => $value1) {
                        $attr_val_ids[] = $value1;
                    }
                }
            }
        }

        unset($data['attr_val_ids']);

        if (isset($key)) unset($key);

        //所选中的属性值
//        return json($data['goods_attr_val']);
        if (!empty($attr_select_ids)) {
            foreach ($data['goods_attr_val'] as $key) {
                foreach ($attr_val_ids as $key1 => $value) {
                    if ($key['id'] == $value && $key['attr_value'] != '') {
                        $data['attr_val_ids'][] = $value;
                        $data['attr_val'][] = $key['attr_value'];
                    }
                }
            }
            $data['attr_val_ids'] = implode(',', $data['attr_val_ids']);
            $data['attr_val'] = implode(',', $data['attr_val']);
        } else {
            $data['attr_val_ids'] = '';
            $data['attr_val'] = '';
        }
        //处理筛选规格
        if (isset($data['screen_val_ids']) && !empty($data['screen_val_ids'])) {
            $data['screen_val_ids'] = implode(',', $data['screen_val_ids']);
        } else {
            $data['screen_val_ids'] = '';
        }

        //会员价格取出
        if (isset($data['rank_price'])) {
            $rank_price = $data['rank_price'];
            unset($data['rank_price']);
        }
        $data['goods_attr'] = json_encode($data['goods_attr'], JSON_UNESCAPED_UNICODE);
        $data['goods_attr_val'] = json_encode($data['goods_attr_val'], JSON_UNESCAPED_UNICODE);
        $goods_model->startTrans();
        try {
            $res = $goods_model->update($data);
            if (!$res) {
                throw new Exception('操作失误，请刷新后重新尝试', 1004);
            }
            //商品id
            $goods_id = $data['id'];

            //如果sku组合存在
            if (isset($sku_group)) {
                //sku组合入库

                //软删除 商品规则会员价格表
                $sku_rank_price_model->where(['goods_id' => $goods_id])->update(['delete_time' => time()]);
                //软删除 规格表
                $sku_model->where(['goods_id' => $goods_id])->update(['delete_time' => time()]);

                foreach ($sku_group as $key => $value) {     //$key 为 组合成的id     value 分 goods_num  goods_price   rank_price
                    //入库 goods_sku表
//                    $sku_model->where(['sku_group'=>$key])->update(['goods_num' => $value['goods_num'],'goods_price'=> $value['goods_price']]);
                    $arr = [
                        'goods_num' => $value['goods_num'],
                        'goods_price' => $value['goods_price'],
                        'sku_group' => $key,
                        'goods_id' => $goods_id,
                        'create_time' => time(),
                    ];
                    $sku_model->insert($arr);
                    $sku_id = $sku_model->getLastInsID();
                    // 入库商品规格会员价格表      需要  sku_id goods_id rank_id
                    foreach ($value['rank_price'] as $key1 => $value1) {
                        $arr = [
                            'goods_sku_id' => $sku_id,
                            'rank_id' => $key1,
                            'goods_id' => $goods_id,
                            'price' => $value1,
                            'create_time' => time(),
                        ];
                        $sku_rank_price_model->insert($arr);
                    }
                }
            } else {      //如果不存在
                //会员价格入库
                if (isset($rank_price)) {
                    unset($temp);
                    $rank_price_data = [];
                    foreach ($rank_price as $key => $value) {
                        if ($value === '') {
                            $temp = $data['price'];
                        } else {
                            $temp = $value;
                        }
                        if (!$rank_price_model->where(['goods_id' => $goods_id])->where(['rank_id' => $key])->find()) {
                            $temp_data = [
                                'goods_id' => $goods_id,
                                'rank_id' => $key,
                                'price' => $temp,
                                'create_time' => time(),
                            ];
                            $rank_price_model->insert($temp_data);
                        } else {
                            $rank_price_model->where(['goods_id' => $goods_id])->where(['rank_id' => $key])->update(['price' => $temp]);
                        }
                    }
                }
            }
            $goods_model->commit();
        } catch (Exception $e) {
            $goods_model->rollback();
            return json(['code' => 0, 'msg' => $e->getMessage()]);
        }
        return json(['code' => '1', 'msg' => '修改成功']);
    }

    public function delete(Request $request)
    {
        $id = $request->post('id');
        $goods_model = new GoodsModel();
        $goods_model->where(['id' => $id])->update(['delete_time' => time()]);
        return json(['code' => 1, 'msg' => '删除成功']);
    }
}