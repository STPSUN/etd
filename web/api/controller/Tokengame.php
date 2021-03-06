<?php

namespace web\api\controller;

use think\Exception;

class Tokengame extends \web\api\controller\ApiBase {

    public function gameInfo() {
//        $this->assign('title', 'P3D');
//        $this->assign('loadPrice', 'getPrice');
        $data = $this->getBalance();
        $data['token_amount'] = $this->getPrice();

        $m = new \addons\fomo\model\Conf();
        $total_token_amount = $m->getValByName('total_token_amount');
        $total_token_bonus = $m->getValByName('total_token_bonus');
        $data['total_token_amount'] = $total_token_amount;
        $data['total_token_bonus'] = $total_token_bonus;

        $current_days = $m->getValByName('total_days');

        $data['daily_avg_amount'] = $total_token_amount / $current_days;
        $data['daily_avg_bonus'] = $total_token_bonus / $current_days;

        $tokenRecordM = new \addons\fomo\model\TokenRecord();
        $data['total_token_num'] = $tokenRecordM->getTotalToken();

        return $this->successJSON($data);
    }

    /**
     * 购买
     * 每购买一个token ,则token价格递增
     * 买入10%分配给用户
     */
    public function buy() {
        if (IS_POST) {
            if ($this->user_id <= 0) {
                return $this->failJSON('您还未登录');
            }
            $p3d_num = $this->_post('buy_p3d_num'); //用户输入的p3d个数
            $coinM = new \addons\config\model\Coins();
            $coin = $coinM->getCoinByName(); //获取eth id
            $coin_id = $coin['id'];
            $balanceM = new \addons\member\model\Balance();
            $balance = $balanceM->getBalanceByCoinID($this->user_id, $coin_id);
            if (empty($balance)) {
                return $this->failJSON('余额不足');
            }
            $confM = new \addons\fomo\model\Conf();
            $token_float = $confM->getValByName('token_float'); //token 浮动值
            $token_amount = $confM->getValByName('token_amount'); //token 当前价格
            $token_total_price = iterativeInc($token_amount, $p3d_num, $token_float); //总金额
            if ($balance['amount'] < $token_total_price) {
                return $this->failJSON('余额不足');
            }
            $token_trading_rate = $confM->getValByName('token_trading_rate'); //token 扣除百分比
            try {
//                扣除用户余额
                $balance['before_amount'] = $balance['amount'];
                $balance['amount'] = $balance['amount'] - $token_total_price;
                $balance['update_time'] = NOW_DATETIME;
                $balanceM->save($balance);
//                token 用户令牌+
                $tokenRecordM = new \addons\fomo\model\TokenRecord();
                $user_token = $tokenRecordM->updateTokenBalance($this->user_id, $p3d_num, true);
//                浮动价+ 
                $token_amount = $token_amount + $p3d_num * $token_float; //浮动后价格
                $price['id'] = $confM->getID('token_amount');
                $price['parameter_val'] = $token_amount;
                $confM->save($price);
                //更新p3d总数
                $total_token_amount = $confM->getDataByName('total_token_amount');
                $total_token_amount['parameter_val'] = $total_token_amount['parameter_val'] + $token_total_price;
                $confM->save($total_token_amount);
                $sequeueM = new \addons\fomo\model\BonusSequeue();
                if ($token_trading_rate > 0) {
                    $p3d_amount = $this->countRate($token_total_price, $token_trading_rate); //发放的分红
                    $sequeueM->addSequeue($this->user_id, $coin_id, $p3d_amount, 0, 0);
                    $total_token_bonus = $confM->getDataByName('total_token_bonus');
                    $total_token_bonus['parameter_val'] = $total_token_bonus['parameter_val'] + $p3d_amount;
                    $confM->save($total_token_bonus);
                    //更新奖励总额
                    return $this->successJSON();
                }
            } catch (\Exception $ex) {
                return $this->failJSON($ex->getMessage());
            }
        } else {
            echo urldecode('%E5%87%A1%E6%89%80%E6%9C%89%E7%9B%B8%EF%BC%8C%E7%9A%86%E6%98%AF%E8%99%9A%E5%A6%84%E3%80%82%E8%8B%A5%E8%A7%81%E8%AF%B8%E7%9B%B8%E9%9D%9E%E7%9B%B8%EF%BC%8C%E5%8D%B3%E8%A7%81%E5%A6%82%E6%9D%A5%E3%80%82');
        }
    }

    /*
     * 卖出
     * 卖出10%归平台
     */

    public function sale() {
        if (IS_POST) {
            if ($this->user_id <= 0) {
                return $this->failData('您还未登录');
            }
            $p3d_num = $this->_post('sale_p3d_num');
        } else {
            $message = urldecode('%E4%B8%80%E5%88%87%E6%9C%89%E4%B8%BA%E6%B3%95%EF%BC%8C%E5%A6%82%E6%A2%A6%E5%B9%BB%E6%B3%A1%E5%BD%B1%EF%BC%8C%E5%A6%82%E9%9C%B2%E4%BA%A6%E5%A6%82%E7%94%B5%EF%BC%8C%E5%BA%94%E4%BD%9C%E5%A6%82%E6%98%AF%E8%A7%82%E3%80%82');
            return $this->failJSON($message);
        }
    }

    public function getBalance() {
        if ($this->user_id <= 0) {
            return $this->failData('您还未登录');
        }
        $m = new \addons\fomo\model\TokenRecord();
        $total_token = $m->getTotalToken($this->user_id);
        $m = new \addons\fomo\model\Conf();
        $token_amount = $m->getValByName('token_amount');
        $total_token_eth = $total_token * $token_amount;
        $coinM = new \addons\config\model\Coins();
        $eth = $coinM->getCoinByName();
        $coin_id = $eth['id'];
        $RewardRecordM = new \addons\fomo\model\RewardRecord();
        $total_token_bonus = $RewardRecordM->getUserTotal($this->user_id, $coin_id);
        $data['total_token'] = $total_token;
        $data['total_token_eth'] = $total_token_eth;
        $data['total_token_bonus'] = $total_token_bonus;
        return $data;
    }

    public function getPrice() {
        $m = new \addons\fomo\model\Conf();
        $token_amount = $m->getValByName('token_amount');
        return $token_amount;
    }

}
