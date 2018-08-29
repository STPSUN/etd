<?php

namespace web\api\controller;

class Keygame extends \web\api\controller\ApiBase
{
    /**
     *
     */
    public function gameInfo()
    {
//      $this->assign('title', 'P3D');
        $game_id = $this->_get("game_id");
        if(!$game_id){
            return $this->failJSON('获取游戏数据失败');
        }
        //判断游戏是否结束
        $data['list'] = $this->getTeams($game_id);
        if (empty($data['list'])) {
            return $this->failJSON('数据加载失败');
        }
        $data['inc'] = $this->getInc();
        if (empty($data['inc'])) {
            return $this->failJSON('数据加载失败');
        }
        return $this->successJSON($data);
    }


    /**
     *
     */
    public function buy()
    {
        if (IS_POST) {
            //投注 需要验证
            if ($this->user_id <= 0) {
                return $this->failJSON('请先登录');
            }
            $game_id = $this->_post('game_id');
            $team_id = $this->_post('team_id');
            $key_num = $this->_post('key_num'); //数量
            //是否有上级,余额是否足够,是否空投
            $gameM = new \addons\fomo\model\Game();
            $game = $gameM->getDetail($game_id);
            $end_game_time = $game['end_game_time'];
            if ($end_game_time <= time()) {
                return $this->failJSON('游戏已经结束');
            }
            $coin_id = $game['coin_id']; //币种
            $balanceM = new \addons\member\model\Balance();
            $balance = $balanceM->getBalanceByCoinID($this->user_id, $coin_id);
            if (empty($balance)) {
                return $this->failJSON('余额不足');
            }
            $priceM = new \addons\fomo\model\KeyPrice();
            $current_price_data = $priceM->getGameCurrentPrice($game_id); //游戏当前价格
            $current_price = $current_price_data['key_amount'];
            $confM = new \addons\fomo\model\Conf();
            $key_inc_amount = $confM->getValByName('key_inc_amount'); //key递增值
            $key_total_price = iterativeInc($current_price, $key_num, $key_inc_amount); //总金额
            $key_total_price = round($key_total_price, 8);
            if ($key_total_price > $balance['amount']) {
                return $this->failJSON('余额不足');
            }
            $teamM = new \addons\fomo\model\Team();
            $team_config = $teamM->getConfigByFields($team_id);
            if (empty($team_config)) {
                return $this->failJSON('所选团队不存在');
            }
            try {
                //扣除用户余额
                $balance['before_amount'] = $balance['amount'];
                $balance['amount'] = $balance['amount'] - $key_total_price;
                $balance['update_time'] = NOW_DATETIME;
                $balanceM->save($balance);
                $userM = new \addons\member\model\MemberAccountModel();
                $pid = $userM->getPID($this->user_id);
                //            $fund_rate = $confM->getValByName('buy_fund_rate');
                //            $fund_amount = $key_total_price * fund_rate / 100; //基金比率
                $gameM->startTrans();
                if (!empty($pid)) {
                    $invite_rate = $confM->getValByName('invite_rate');
                    $invite_amount = $this->countRate($key_total_price, invite_rate); //邀请奖励
                    $pidBalance = $balanceM->updateBalance($pid, $invite_amount, $coin_id, true);
                }
                $drop_amount = 0;
                $is_drop = $confM->getValByName('is_drop');
                if ($is_drop == 1) {
                    $drop_rate = $confM->getValByName('drop_rate');
                    $drop_amount = $this->countRate($key_total_price, $drop_rate); //空投金额
                }
                //空投比率,基金比率,推荐比率,价格递增
                //战队:投注p3d,f3d奖励队列,奖池+,用户key+,时间+
                $pool_amount = $this->countRate($key_total_price, $team_config['pool_rate']); //进入奖池金额
                $release_amount = $key_total_price - $pool_amount; //已发金额
                $buy_inc_second = $confM->getValByName('buy_inc_second');
                $inc_time = $key_num * $buy_inc_second; //游戏增加时间
//                更新数据 
//                用户key+
                $keyRecordM = new \addons\fomo\model\KeyRecord(); //用户key记录
                $save_key = $keyRecordM->saveUserKey($this->user_id, $team_id, $game_id, $key_num);
//                奖池+ ,时间+
                $game['end_game_time'] = $game['end_game_time'] + $inc_time;
                $game['total_buy_seconds'] = $game['total_buy_seconds'] + $inc_time;
                $game['total_amount'] = $game['total_amount'] + $key_total_price;
                $game['pool_total_amount'] = $game['pool_total_amount'] + $pool_amount;
                $game['release_total_amount'] = $game['release_total_amount'] + $release_amount;
                $game['update_time'] = NOW_DATETIME;
                $gameM->save($game);
//                战队总额+
                $teamTotalM = new \addons\fomo\model\TeamTotal();
                $team_total = $teamTotalM->getDataByWhere($team_id, $game_id, $coin_id);
                $team_total['before_total_amount'] = $team_total['total_amount'];
                $team_total['total_amount'] = $team_total['total_amount'] + $key_total_price;
                $team_total['update_time'] = NOW_DATETIME;
                $teamTotalM->save($team_total);
//                key 价格+ 
                $current_price_data['key_amount'] = $current_price + $key_inc_amount * $key_num;
                $current_price_data['update_time'] = NOW_DATETIME;
                $priceM->save($current_price_data);
//                战队:投注p3d,f3d奖励队列
                $sequeueM = new \addons\fomo\model\BonusSequeue();
                if ($team_config['p3d_rate'] > 0) {
                    $p3d_amount = $this->countRate($key_total_price, $team_config['p3d_rate']); //发放给p3d用户金额
                    $sequeueM->addSequeue($this->user_id, $coin_id, $p3d_amount, 0, 1, $game_id);
                }
                $f3d_amount = $this->countRate($key_total_price, $team_config['f3d_rate']); //发放给f3d用户金额
                $sequeueM->addSequeue($this->user_id, $coin_id, $f3d_amount, 1, 1, $game_id, $team_id);
                $gameM->commit();
                return $this->successJSON();
            } catch (\Exception $ex) {
                $gameM->rollback();
                return $this->failJSON($ex->getMessage());
            }
        }
    }

    /**
     * @param int $game_id
     * @return mixed
     */
    private function getTeams($game_id = 1)
    {
        $m = new \addons\fomo\model\Team();
        $list = $m->getTeamsByGame($game_id);
        $totalM = new \addons\fomo\model\TeamTotal;
        $filter = "game_id = {$game_id}";
        $total = $totalM->getSum($filter, "total_amount");
        foreach($list as &$val){
            $val['team_proportion'] = $total > 0 ? bcdiv( $val['total_amount'],$total,2) * 100 : 0;
        }

        return $list;
    }

    /**
     * @return string
     */
    private function getInc()
    {
        $m = new \addons\fomo\model\Conf();
        $inc = $m->getValByName('key_inc_amount');
//        $this->assign('inc', $inc);
        return $inc;
    }

    /**
     *
     */
    public function getGame()
    {
        $m = new \addons\fomo\model\Game();
        $game = $m->getRunGame();
        if (empty($game)) {
            return $this->failJSON('等待开启新一轮');
        }
        $game = $game[0];
        $totalM  = new \addons\fomo\model\TeamTotal();
        $filter = "game_id = {$game['id']}";
        $total_key = $totalM->getSum($filter, 'total_amount');
        $game['total_key'] = $total_key;


        $KeyPriceM = new \addons\fomo\model\KeyPrice();
        $price = $KeyPriceM->getGameCurrentPrice($game['id']);
        $game['key_price'] = $price;

        $maketM = new \web\api\model\MarketModel();
        $rate = $maketM->getCnyRateByCoinId($game['coin_id']);
        $game['cny_rate'] = $rate;
//            $game['end_game_time'] = date('Y/m/d H:i:s', $game['end_game_time']);
        return $this->successJSON($game);
    }

    /**
     *
     */
    public function getTeamTotal()
    {
        $game_id = $this->_get('game_id');
        $m = new \addons\fomo\model\TeamTotal();
        $data = $m->getTotalByGameId($game_id);
        if (!empty($data)) {
            return $this->successJSON($data);
        } else {
            return $this->failJSON('战队总额数据加载失败');
        }
    }

    /**
     * 获取key价格
     */
    public function getPrice()
    {
        $game_id = $this->_get('game_id');
        $m = new \addons\fomo\model\KeyPrice();
        $data = $m->getGameCurrentPrice($game_id);
//        return $this->successJSON($data['key_amount']);
        if (!empty($data['key_amount'])) {
            return $this->successJSON($data);
        } else {
            return $this->failJSON('数据加载失败');
        }
    }

    /**
     *
     */
    public function getKeys()
    {
        $token = $this->_get('token',null);
        if(!$token){
            return $this->failJSON("请先登录");
        }
        $this->user_id = intval($this->getGlobalCache($token)); //redis中获取user_id
        if (empty($this->user_id)) {
            return $this->failJSON("登录已失效，请重新登录");
        }
        $game_id = $this->_get('game_id');
        $coin_id = $this->_get('coin_id');
        $keyRecordM = new \addons\fomo\model\KeyRecord();
        $key_num = $keyRecordM->getTotalByGameID($this->user_id, $game_id); //持有游戏key数量
        $data['key_num'] = $key_num;
        $rewardM = new \addons\fomo\model\RewardRecord();
        $data['invite_reward'] = $rewardM->getTotalByType($this->user_id, $coin_id); //邀请分红
        $data['other_reward'] = $rewardM->getTotalByType($this->user_id, $coin_id, '0,1,2'); //分红总量
        $balanceM = new \addons\member\model\Balance();
        $balance = $balanceM->getBalanceByCoinID($this->user_id, $coin_id); //账户币种余额
        $data['balance'] = $balance ? $balance['amount'] : 0;

        $rewardM = new \addons\fomo\model\RewardRecord();
        $data['current_game_total_reward'] = $rewardM->getUserTotal($this->user_id, $coin_id, $game_id); //获取游戏投入总量
        return $this->successJSON($data);
    }

    /**
     *
     */
    public function getBalance()
    {
        $token = $this->_get('token',null);
        if(!$token){
            return $this->failJSON("请先登录");
        }
        $this->user_id = intval($this->getGlobalCache($token)); //redis中获取user_id
        if (empty($this->user_id)) {
            return $this->failJSON("登录已失效，请重新登录");
        }
        $game_id = $this->_get('game_id');
        $coin_id = $this->_get('coin_id');
        $keyRecordM = new \addons\fomo\model\KeyRecord();
        $key_num = $keyRecordM->getTotalByGameID($this->user_id, $game_id); //持有游戏key数量
        $data['key_num'] = $key_num;
        $rewardM = new \addons\fomo\model\RewardRecord();
        $data['invite_reward'] = $rewardM->getTotalByType($this->user_id, $coin_id); //邀请分红
        $data['other_reward'] = $rewardM->getTotalByType($this->user_id, $coin_id, '0,1,2'); //分红总量
        $balanceM = new \addons\member\model\Balance();
        $balance = $balanceM->getBalanceByCoinID($this->user_id, $coin_id); //账户币种余额
        $data['balance'] = $balance ? $balance['amount'] : 0;

        $rewardM = new \addons\fomo\model\RewardRecord();
        $data['current_game_total_reward'] = $rewardM->getUserTotal($this->user_id, $coin_id, $game_id); //获取游戏投入总量
        return $this->successJSON($data);
    }

}
