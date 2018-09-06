<?php

namespace web\mobile\controller;
use think\Request;
use think\Validate;

/**
 * 前端首页控制器
 */
class Index extends Base {

    private $ETD = 13;
    private $USDT = 1;
    private $balanceM;
    private $userM;

    public function _initialize()
    {
        parent::_initialize(); // TODO: Change the autogenerated stub
        $this->balanceM = new \addons\member\model\Balance();
        $this->userM = new \addons\member\model\MemberAccountModel();
    }

    public function index(){
        $balanceUSDT = $this->balanceM->where(['user_id' => $this->user_id, 'coin_id' => $this->USDT])->find();
        $balanceETD = $this->balanceM->where(['user_id' => $this->user_id, 'coin_id' => $this->ETD])->find();
        $user = $this->userM->getDetail($this->user_id);

        $usdt = empty($balanceUSDT['amount']) ? 0 : $balanceUSDT['amount'];
        $etd = empty($balanceETD['amount']) ? 0 : $balanceETD['amount'];

        $data = array(
            'usdt'  => $usdt,
            'etd'   => $etd,
            'username'  => $user['username'],
            'level' => $user['level'] == 1 ? '普通会员' : 'VIP'
        );

        $this->assign('data',$data);
        return $this->fetch();
    }

    public function toTeam()
    {
        return $this->fetch('team');
    }

    /**
     * 团队列表
     */
    public function team()
    {
        $data = $this->recursionTeam($this->user_id);

        return $this->successData($data);
    }

    /**
     * 递归团队成员
     */
    private function recursionTeam($id,&$result=array())
    {
        $data = $this->userM->where('pid',$id)->select();
        $user = $this->userM->where('id',$id)->find();
        foreach ($data as $v)
        {
            $temp = array(
                'username' => $v['username'],
                'phone'    => $v['phone'],
                'invite'   => $user['username'],
            );
            $result[] = $temp;

            $users = $this->userM->where('pid',$v['id'])->select();
            if(!empty($users))
            {
                $this->recursionTeam($v['id'],$result);
            }
        }

        return $result;
    }

    public function toTransAccount()
    {
        return $this->fetch('transaccount');
    }

    public function transAccount()
    {
        $param = Request::instance()->post();
        $validate = new Validate([
            'username'  => 'require',
            'num'   => 'require',
            'pay_password' => 'require',
        ]);

        if(!$validate->check($param))
            return $this->failData($validate->getError());

        $username = $param['username'];
        $num = $param['num'];
        $pay_password = md5($param['pay_password']);

        $accept_user = $this->userM->where('username',$username)->find();
        if(empty($accept_user))
            return $this->failData('接收账号不存在');

        $verify = $this->balanceM->verifyStock($this->user_id,$this->ETD,$num);
        if(!$verify)
            return $this->failData('ETD余额不足');

        $user = $this->userM->getDetail($this->user_id);
        if($user['pay_password'] != $pay_password)
            return $this->failData('密码错误');

        $balance = $this->balanceM->where(['user_id' => $this->user_id, 'coin_id' => $this->ETD])->find();

        $this->balanceM->startTrans();
        try
        {
            //转出方余额更新
            $this->balanceM->updateBalance($this->user_id,$num,$this->ETD,false);
            //接收方余额更新
            $this->balanceM->updateBalance($accept_user['id'],$num,$this->ETD,true);
            //添加交易记录
            $recordM = new \addons\member\model\TradingRecord();
            $after_amount = $balance['amount'] - $num;
            $recordM->addRecord($this->user_id,$this->ETD,$num,$balance['amount'],$after_amount,0,0,$accept_user['id']);

            $this->balanceM->commit();
            return $this->successData();
        }catch (\Exception $e)
        {
            $this->balanceM->rollback();
            return $this->failData($e->getMessage());
        }

    }
}























