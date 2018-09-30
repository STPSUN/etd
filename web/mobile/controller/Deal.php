<?php
/**
 * Created by PhpStorm.
 * User: SUN
 * Date: 2018/8/24
 * Time: 16:30
 */

namespace web\mobile\controller;


use function PHPSTORM_META\type;
use think\Request;
use think\Validate;

class Deal extends Base
{
    public function index()
    {
        return $this->fetch();
    }

    public function getList()
    {
        $param = Request::instance()->post();
        $validate = new Validate([
            'type'  => 'require|in:0,1,2,3',
            'coin_id'   => 'require|integer'
        ]);
        if(!$validate->check($param))
            return $this->failData($validate->getError());

        $type = $param['type'];
        $coin_id = $param['coin_id'];

        $m = new \addons\otc\model\OtcOrder();
        $filter = 'coin_id=' . $coin_id;
        $orderby = 'price asc';
        $fields = 'id,user_id,username,buy_username,pay_type,type,price,amount,total_amount,pay_amount,status,add_time';
        try
        {
            switch ($type)
            {
                case 0:
                case 1:
                    $filter .= ' and type=' . $type . ' and status = 0 and user_id !=' . $this->user_id;
                    break;
                case 2:
                    $filter .= ' and (user_id=' . $this->user_id . ' or ' . ' buy_user_id=' . $this->user_id . ') and status In(0,1,2)';
                    $orderby = 'status asc';
                    break;
                case 3:
                    $filter .= ' and (user_id=' . $this->user_id . ' or ' . ' buy_user_id=' . $this->user_id . ') and status = 3';
                    $orderby = 'status desc';
                    break;
            }

            $list = $m->getList($this->getPageIndex(),999,$filter,$fields,$orderby);
            $userM = new \addons\member\model\MemberAccountModel();
            if(!empty($list))
            {
                for ($i = 0; $i < count($list); $i++)
                {
                    $list[$i]['is_affirm'] = 0;
                    switch ($list[$i]['status'])
                    {
                        case 0:
                            $list[$i]['status_text'] = '排队中';
                            break;
                        case 1:
                            $list[$i]['status_text'] = '已匹配';
                            break;
                        case 2:
                            $list[$i]['status_text'] = '已打款';
                            if($list[$i]['user_id'] == $this->user_id)
                                $list[$i]['is_affirm'] = 1;
                            break;
                        case 3:
                            $list[$i]['status_text'] = '已完成';
                            break;
                    }

                    $list[$i]['add_time'] = date('Y-m-d',strtotime($list[$i]['add_time']));

                    if($type == 1)
                    {
                        $deal_num = $userM->where('id',$list[$i]['user_id'])->column('deal_num');
                        $trust_num = $userM->where('id',$list[$i]['user_id'])->column('trust_num');
                        $list[$i]['deal_num'] = $deal_num[0];
                        $list[$i]['trust_num'] = $trust_num[0];
                    }else
                    {
                        $deal_num = $userM->where('id',$this->user_id)->column('deal_num');
                        $trust_num = $userM->where('id',$this->user_id)->column('trust_num');
                        $list[$i]['deal_num'] = $deal_num[0];
                        $list[$i]['trust_num'] = $trust_num[0];
                    }
                }
            }

            $data = array(
                'list'  => $list,
//                'deal_num'  => $deal_num[0],
//                'trust_num' => $trust_num[0]
            );

            return $this->successData($data);
        }catch (\Exception $e)
        {
            return $this->failData($e->getMessage());
        }

    }

    /**
     * 挂卖
     * @return array
     */
    public function postSaleOrder()
    {
        if(!IS_POST)
        {
            return $this->failData('illegal request');
        }

        $param = Request::instance()->post();
        $type = 0;
        $velivade = new Validate([
            'num|卖出数量'   => 'require|number|>=:100',
            'pay_password'  => 'require',
            'coin_id'   => 'require|in:13,14',
            'price'     => 'require',
        ]);
        if(!$velivade->check($param))
            return $this->failData($velivade->getError());

        $coin_id = $param['coin_id'];
        $num = $param['num'];
        $price = $param['price'];
        $pay_password = md5($param['pay_password']);
        $userM = new \addons\member\model\MemberAccountModel();
        $user = $userM->getDetail($this->user_id);
        if($user['pay_password'] != $pay_password)
        {
            return $this->failData('支付密码错误');
        }

        $payM = new \addons\otc\model\PayConfig();
        $pay_data = $payM->where('user_id',$this->user_id)->find();
        if(empty($pay_data))
            return $this->failData('未设置收款方式');

        $paramM = new \web\common\model\sys\SysParameterModel();
        $usdt_cny = $paramM->getValByName('usdt_cny');
        $float_rate = $paramM->getValByName('deal_float_rate');
        $price_float = $this->countRate($usdt_cny,$float_rate);
        $price_min = $usdt_cny - $price_float;
        $price_max = $usdt_cny + $price_float;

        if(($price < $price_min) || ($price > $price_max))
            return $this->failData('价格上下浮动不能超过' . $float_rate . '%');

        $pay_detail_json = json_encode($pay_data,JSON_UNESCAPED_UNICODE);

        try
        {
            $balanceM = new \addons\member\model\Balance();
            $balanceM->startTrans();
            $balance = $balanceM->getBalanceByCoinID($this->user_id,$coin_id);
            if(!$balanceM->verifyStock($this->user_id,$param['coin_id'],$num))
                return $this->failData('余额不足');

            $coinConfM = new \addons\config\model\CoinConf();
//            $price = $coinConfM->getPriceByCoinId($coin_id);
            $pay_amount = $num * $price;   //需支付金额
            $m = new \addons\otc\model\OtcOrder();
            $id = $m->addOrder($this->user_id,$coin_id,$type,$num,0,$num,$price,$pay_amount,0,$pay_detail_json,'');
            if($id <= 0)
            {
                $balanceM->rollback();
                return $this->failData('订单提交失败');
            }

            $before_amount = $balance['amount'];
            $balance['before_amount'] = $before_amount;
            $balance['amount'] = $before_amount - $num;
            $balance['otc_frozen_amount'] = $balance['otc_frozen_amount'] + $num;
            $balance['update_time'] = NOW_DATETIME;
            $is_save = $balanceM->save($balance);
            if($is_save > 0)
            {
                $balanceM->commit();
                return $this->successData();
            }else
            {
                $balanceM->rollback();
                return $this->failData('余额更新失败');
            }
        }catch (\Exception $e)
        {
            return $this->failData($e->getMessage());
        }
    }


    /**
     * to挂卖
     * @return array|mixed
     */
    public function selling()
    {
        $param = Request::instance()->param();
        $validate = new Validate([
            'id'    => 'require|in:13,14',
        ]);
        if(!$validate->check($param))
            return $this->failData($validate->getError());

        $coin_id = $param['id'];

        $coinConfM = new \addons\config\model\CoinConf();
//        $price = $coinConfM->getPriceByCoinId($coin_id);

        $paramM = new \web\common\model\sys\SysParameterModel();
        $usdt_cny = $paramM->getValByName('usdt_cny');

        $this->assign('price',$usdt_cny);
        return $this->fetch();
    }

    /**
     * 挂买
     * @return array
     */
    public function postBuyOrder()
    {
        if(!IS_POST)
            return $this->failData('illegal request');

        $param = Request::instance()->post();
        $validate = new Validate([
            'num|买入数量'  => 'require|number|>=:1',
            'pay_password'  => 'require',
            'coin_id'       => 'require|integer'
        ]);

        $type = 1;
        $coin_id = $param['coin_id'];
        $num = $param['num'];
        $price = $param['price'];

        if(!$validate->check($param))
            return $this->failData($validate->getError());

        $pay_password = md5($param['pay_password']);
        $userM = new \addons\member\model\MemberAccountModel();
        $user = $userM->getDetail($this->user_id);

        if($user['pay_password'] != $pay_password)
            return $this->failData('支付密码错误');

        $paramM = new \web\common\model\sys\SysParameterModel();
        $usdt_cny = $paramM->getValByName('usdt_cny');
        $float_rate = $paramM->getValByName('deal_float_rate');
        $price_float = $this->countRate($usdt_cny,$float_rate);
        $price_min = $usdt_cny - $price_float;
        $price_max = $usdt_cny + $price_float;

        if(($price < $price_min) || ($price > $price_max))
            return $this->failData('价格上下浮动不能超过' . $float_rate . '%');

        $coinConfM = new \addons\config\model\CoinConf();
//        $price = $coinConfM->getPriceByCoinId($coin_id);
        $pay_amount = $num * $price;
        try
        {
            $m = new \addons\otc\model\OtcOrder();
            $id = $m->addOrder($this->user_id,$coin_id,$type,$num,0,$num,$price,$pay_amount,0,'','');
            if($id > 0)
                return $this->successData();
            else
                return $this->failData('订单提交失败');
        }catch (\Exception $e)
        {
            return $this->failData($e->getMessage());
        }
    }

    /**
     * 打款
     */
    public function remit()
    {
        $param = Request::instance()->param();
        $validate = new Validate([
           'id'   => 'require'
        ]);
        if(!$validate->check($param))
            return $this->failData($validate->getError());

        $order_id = $param['id'];
        $orderM = new \addons\otc\model\OtcOrder();
        $payM = new \addons\otc\model\PayConfig();
        $userM = new \addons\member\model\MemberAccountModel();

        $order = $orderM->where('id',$order_id)->field('amount,user_id,status,id')->find();
        $phone = $userM->where('id',$order['user_id'])->column('phone');
        $pay_data = $payM->where('user_id',$order['user_id'])->select();

        $data = array(
            'num'   => $order['amount'],
            'order_id'  => $order['id'],
            'phone' => $phone[0],
            'name'  => '',
            'wechat'    => '',
            'alipay'    => '',
            'bank'  => '',
            'account'   => '',
        );
        foreach ($pay_data as $v)
        {
            switch ($v['type'])
            {
                case 1:
                    $data['wechat'] = $v['qrcode'];
                    break;
                case 2:
                    $data['alipay'] = $v['qrcode'];
                    break;
                case 3:
                    $data['name'] = $v['name'];
                    $data['bank'] = $v['bank_address'];
                    $data['account'] = $v['account'];
                    break;
            }
        }

        switch ($order['status'])
        {
            case 0:
                $data['status'] = '排队中';
                break;
            case 1:
                $data['status'] = '等待打款';
                break;
            case 2:
                $data['status'] = '已打款';
                break;
            case 3:
                $data['status'] = '已完成';
                break;
        }

        $this->assign('data',$data);
        return $this->fetch();
    }


    /**
     * 上传凭证
     * @return array|mixed
     */
    public function upload()
    {
        $param = Request::instance()->param();
        $validate = new Validate([
            'id'   => 'require'
        ]);
        if(!$validate->check($param))
            return $this->failData($validate->getError());

        $this->assign('id',$param['id']);
        return $this->fetch();
    }

    /**
     * to购买
     * @return array|mixed
     */
    public function purchase()
    {
        $param = Request::instance()->param();
        $validate = new Validate([
            'id'    => 'require',
        ]);
        if(!$validate->check($param))
            return $this->failData($validate->getError());

        $coin_id = 13;
        $order_id = $param['id'];

        $coinConfM = new \addons\config\model\CoinConf();
        $orderM = new \addons\otc\model\OtcOrder();
        $amount = $orderM->where('id',$order_id)->column('amount');

        $paramM = new \web\common\model\sys\SysParameterModel();
        $price = $paramM->getValByName('usdt_cny');
//        $price = $coinConfM->getPriceByCoinId($coin_id);
        $total_amount = bcmul($amount[0],$price,2);

        $this->assign('order_id',$order_id);
        $this->assign('amount',$amount[0]);
        $this->assign('price',$price);
        $this->assign('total_amount',$total_amount);
        return $this->fetch();
    }

    /**
     * 下单
     */
    public function placeOrder()
    {
        if(!IS_POST)
            return $this->failData('illegal request');

        $param = Request::instance()->post();
        $validate = new Validate([
            'order_id'  => 'require|integer',
//            'pay_type'  => 'require',
        ]);
        if(!$validate->check($param))
            return $this->failData($validate->getError());

        $order_id = $param['order_id'];
//        $pay_type = $param['pay_type'];

        $m = new \addons\otc\model\OtcOrder();
        $order = $m->getOrderByStatus($order_id);
//        $userM = new \addons\member\model\MemberAccountModel();
//        $user = $userM->getDetail($this->user_id);

        try
        {
            if(empty($order))
                return $this->failData('订单不存在');

            if($order['user_id'] == $this->user_id)
                return $this->failData('无法对自己的当地进行操作');

            $m->startTrans();
            if($order['type'] == 1)
            {
                $payM = new \addons\otc\model\PayConfig();
                $pay_data = $payM->getUserPay($this->user_id);
                if(empty($pay_data))
                {
                    $m->rollback();
                    return $this->failData('未设置收款方式');
                }

                $order['pay_detail_json'] = json_encode($pay_data,JSON_UNESCAPED_UNICODE);

                $coin_id = $order['coin_id'];
                $total_num = $order['total_amount'];

                $balanceM = new \addons\member\model\Balance();
                $balance = $balanceM->getBalanceByCoinID($this->user_id,$coin_id);
                if(!$balanceM->verifyStock($this->user_id,$coin_id,$total_num))
                    return $this->failData('您的余额不满足此订单,无法下单');

                $before_amount = $balance['amount'];
                $balance['before_amount'] = $before_amount;
                $balance['amount'] = $before_amount - $total_num;
                $balance['otc_frozen_amount'] = $balance['otc_frozen_amount'] + $total_num;
                $balance['update_time'] = NOW_DATETIME;
                $is_save = $balanceM->save($balance);

                if($is_save <= 0)
                    return $this->failData('余额更新失败');
            }

            $order['buy_user_id'] = $this->user_id;
            $order['status'] = 1;
            $order['deal_time'] = NOW_DATETIME;
            $order['update_time'] = NOW_DATETIME;
            $res = $m->save($order,'',null,false);
            if($res > 0)
            {
                $m->commit();
                return $this->successData();
            }else{
                $m->rollback();
                return $this->failData('下单失败');
            }

        }catch (\Exception $e)
        {
            $m->rollback();
            return $this->failData($e->getMessage());
        }
    }

    /**
     * to确认收款
     */
    public function affirm()
    {
        $param = Request::instance()->param();
        $validate = new Validate([
            'id'  => 'require|integer',
        ]);
        if(!$validate->check($param))
            return $this->failData($validate->getError());

        $order_id = $param['id'];
        $orderM = new \addons\otc\model\OtcOrder();
        $pic = $orderM->where('id',$order_id)->column('pic');

        $this->assign('order_id',$order_id);
        $this->assign('pic',$pic[0]);
        return $this->fetch();
    }

    /**
     * 确认收款
     */
    public function isAffirm()
    {
        $param = Request::instance()->post();
        $validate = new Validate([
            'order_id'  => 'require|integer',
        ]);
        if(!$validate->check($param))
            return $this->failData($validate->getError());

        $order_id = $param['order_id'];
        $orderM = new \addons\otc\model\OtcOrder();
        $res = $orderM->save([
            'status'    => 3,
        ],['id' => $order_id]);

        if($res > 0)
        {
            return $this->successData();
        }else
        {
            return $this->failData('确认收款失败');
        }

    }
}



















