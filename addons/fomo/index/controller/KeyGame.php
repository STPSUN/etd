<?php

namespace addons\fomo\index\controller;

/**
 * Description of Game
 * f3d游戏界面
 * @author shilinqing
 */
class KeyGame extends Fomobase{
    
    public function index() {
        $this->assign('title','P3D');
        //判断游戏是否结束
        $this->getTeams();
        $this->getInc();
        return $this->fetch();
    }
    
    public function buy(){
        if(IS_POST){
            //投注 需要验证
            if($this->user_id <= 0){
                $url = getUrl('login/index','','member',false);
                $this->redirect($url);
                exit;
            }
            $game_id = $this->_post('game_id');
            $team_id = $this->_post('team_id');
            $key_num = $this->_post('key_num'); //数量
            //是否有上级,余额是否足够,是否空投
            $gameM = new \addons\fomo\model\Game();
            $game = $gameM->getDetail($game_id);
            $end_game_time = $game['end_game_time'];
            if($end_game_time <= time()){
                return $this->failData('游戏已经结束');
            }
            $coin_id = $game['coin_id']; //币种
            $balanceM = new \addons\member\model\Balance();
            $balance = $balanceM->getBalanceByCoinID($this->user_id, $coin_id);
            if(empty($balance)){
               return $this->failData('余额不足'); 
            }
            $priceM = new \addons\fomo\model\KeyPrice();
            $current_price_data = $priceM->getGameCurrentPrice($game_id); //游戏当前价格
            $current_price = $current_price_data['key_amount'];
            $confM = new \addons\fomo\model\Conf();
            $key_inc_amount = $confM->getValByName('key_inc_amount'); //key递增值
            $key_total_price = iterativeInc($current_price, $key_num, $key_inc_amount);//总金额
            $key_total_price = round($key_total_price, 8);
            if($key_total_price > $balance['amount']){
                return $this->failData('余额不足');
            }
            $teamM = new \addons\fomo\model\Team();
            $team_config = $teamM->getConfigByFields($team_id);
            if(empty($team_config)){
               return $this->failData('所选团队不存在'); 
            }
            try{
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
                if(!empty($pid)){
                    $invite_rate = $confM->getValByName('invite_rate');
                    $invite_amount = $this->countRate($key_total_price , invite_rate); //邀请奖励
                    $pidBalance = $balanceM->updateBalance($pid, $invite_amount, $coin_id,true);
                    //to do 添加分红记录 type = 3
                    
                }
                $drop_amount = 0;
                $is_drop = $confM->getValByName('is_drop');
                if($is_drop == 1){
                    $drop_rate = $confM->getValByName('drop_rate');
                    $drop_amount = $this->countRate($key_total_price , $drop_rate); //空投金额
                    //to do 获取空投
                    $this->getAirDrop($drop_rate,$key_num,$key_total_price,$game_id);
                }
                //战队:投注p3d,f3d奖励队列,奖池+,用户key+,时间+
                $pool_amount = $this->countRate($key_total_price , $team_config['pool_rate']);//进入奖池金额
                $release_amount = $key_total_price - $pool_amount; //已发金额
                $buy_inc_second = $confM->getValByName('buy_inc_second');
                $inc_time = $key_num * $buy_inc_second; //游戏增加时间
//                更新数据 
//                用户key+
                $keyRecordM = new \addons\fomo\model\KeyRecord(); //用户key记录
                $save_key = $keyRecordM->saveUserKey($this->user_id,$team_id,$game_id, $key_num);
//                奖池+ ,时间+
                $game['end_game_time'] = $game['end_game_time'] + $inc_time;
                $game['total_buy_seconds'] = $game['total_buy_seconds'] + $inc_time;
                $game['total_amount'] = $game['total_amount'] + $key_total_price;
                $game['pool_total_amount'] = $game['pool_total_amount'] + $pool_amount;
                $game['release_total_amount'] = $game['release_total_amount'] + $release_amount;
                $game['drop_total_amount'] = $game['drop_total_amount'] + $drop_amount;
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
                if($team_config['p3d_rate'] > 0){
                    $p3d_amount = $this->countRate($key_total_price , $team_config['p3d_rate']);//发放给p3d用户金额
                    $sequeueM->addSequeue($this->user_id, $coin_id, $p3d_amount, 0, 1, $game_id);
                    
                }
                $f3d_amount = $this->countRate($key_total_price , $team_config['f3d_rate']);//发放给f3d用户金额
                $sequeueM->addSequeue($this->user_id, $coin_id, $f3d_amount, 1, 1,$game_id, $team_id);
                $gameM->commit();
                return $this->successData();
            } catch (\Exception $ex) {
                $gameM->rollback();
                return $this->failData($ex->getMessage());
            }
            
        }
    }
    
    /**
     * 获取空投
     */
    private function getAirDrop($drop_rate,$key_num,$key_total_price,$game_id){
        $confM = new \addons\fomo\model\Conf();
        $need_key = $confM->getValByName('need_key');

        //判断key数量
        if($key_num < $need_key)
            return;

        //是否中奖
        $win_num = rand(1,100);
        if($win_num > $drop_rate)
            return;

        //奖金比率
        $bonus_rate = $this->getDropRate($key_total_price);
        //奖金
        $bonus = $key_total_price * $bonus_rate;

        try
        {
            //更新空投总额
            $gameM = new \addons\fomo\model\Game();
            $gameM->startTrans();
            $gameM->where('id',$game_id)->setDec('drop_total_amount',$bonus);

            //更新用户资金
            $balanceM = new \addons\member\model\Balance();
            $balance_data = $balanceM->getDetail($this->user_id);
            $balanceM->save([
                'beform_amount' => $balance_data['amount'],
                'amount'    => $balance_data['amount'] + $bonus,
                'total_amount'  => $balance_data['total_amount'] + $bonus
            ],[
                'id' => $this->user_id
            ]);

            //添加分红记录
            $recordM = new \addons\fomo\model\RewardRecord();
            $after_amount = $balance_data['amount'] + $bonus;
            $remark = '空投奖励';
            $recordM->addRecord($this->user_id,1,$balance_data['amount'],$bonus,$after_amount,0,$game_id,$remark);

            $gameM->commit();
        }catch (\Exception $e)
        {
            $gameM->rollback();
        }
    }

    //奖金比率
    private function getDropRate($num=null){
        $airdropM = new \addons\fomo\model\Airdrop();
        $result = $airdropM->where(function ($query) use ($num) {
            $query->where('min','<=',$num)->where('max','>=',$num)->where('min','<>',0);
        })->whereOr(function ($query) use ($num) {
            $query->where('max','<=',$num)->where('min',0);
        })->find();

        return $result['rate'];
    }


    private function getTeams(){
        $m = new \addons\fomo\model\Team();
        $filter = 'status=1';
        $fields = "id,name,detail,pic";
        $list = $m->getDataList(-1,-1,$filter,$fields,'id asc');
        $this->assign('teams',$list);
    }
    
    private function getInc(){
        $m = new \addons\fomo\model\Conf();
        $inc = $m->getValByName('key_inc_amount');
        $this->assign('inc',$inc);
    }


    public function getGame(){
        $m = new \addons\fomo\model\Game();
        $game = $m->getRunGame();
        if(!empty($game)){
            $game = $game[0];
            $end_game_time = $game['end_game_time'];
            if($end_game_time <= time()){
                //if 游戏的当前结束时间小于等于当前时间,则结束游戏
                //奖池分配:
                //胜利者所在战队比率: 进入下一局比率,战队f3d玩家分红比率, p3d玩家分红比率
                try{
                    $m->startTrans();
                    //更新游戏状态为结束 status = 2 
                    $game['status'] = 2;
                    $is_over = $m->save($game);
                    if($is_over > 0){
                        $game_id = $game['id'];
                        $coin_id = $game['coin_id'];
                        $pool_total_amount = $game['pool_total_amount']; //奖池总数
                        //获取最后投注者
                        $keyRecordM = new \addons\fomo\model\KeyRecord();
                        $last_winner = $keyRecordM->getLastWinner($game_id);
                        $winner_user_id = $last_winner['user_id'];
                        //本轮游戏胜利者比率
                        $winner_amount = $this->countRate($pool_total_amount, $game['winner_rate']);//胜利者所得
                        //更新用户余额 , 添加分红记录 type = 2
                        $balanceM = new \addons\member\model\Balance();
                        $balance = $balanceM->updateBalance($winner_user_id, $winner_amount, $coin_id, true);
                        $recordM = new \addons\fomo\model\RewardRecord();
                        $before_amount = $balance['before_amount'];
                        $after_amount = $balance['amount'];
                        $type = 2;
                        $remark = '胜利者分红';
                        $recordM->addRecord($winner_user_id, $coin_id, $before_amount, $winner_amount, $after_amount, $type, $game_id, $remark);
                        
                        $winner_team_id = $last_winner['team_id']; //胜利者队伍id
                        $team_total_amount = $this->countRate($pool_total_amount, $game['team_rate']);//战队所得
                        //获取战队游戏结束分红配置
                        $teamM = new \addons\fomo\model\Team();
                        $end_game_config = $teamM->getConfigByFields($winner_team_id, 'to_next_rate,end_f3d_rate,end_p3d_rate');
                        $to_next_amount = 0;
                        if($end_game_config['to_next_rate'] > 0){
                            $to_next_amount = $this->countRate($team_total_amount, $end_game_config['to_next_rate']);
                            //更新进入下一局金额
                            $game['to_next_amount'] = $to_next_amount;
                            $m->save($game);
                        }
                        $queueM = new \addons\fomo\model\BonusSequeue();
                        $end_f3d_amount = 0;
                        if($end_game_config['end_f3d_rate'] > 0){
                            $end_f3d_amount = $this->countRate($team_total_amount, $end_game_config['end_f3d_rate']);
                            //添加队列 scene = 2 ,type=1
                            $type = 1;
                            $scene = 2;
                            $queueM->addSequeue($winner_user_id, $coin_id, $end_f3d_amount, $type, $scene, $game_id, $winner_team_id);
                            
                        }
                        $end_p3d_amount = 0;
                        if($end_game_config['end_p3d_rate'] > 0){
                            $end_p3d_amount = $this->countRate($team_total_amount, $end_game_config['end_p3d_rate']);
                            //添加队列 scene = 2 ,type=0
                            $type = 0;
                            $scene = 2;
                            $queueM->addSequeue($winner_user_id, $coin_id, $end_p3d_amount, $type, $scene, $game_id);
                        }
                        $m->commit();
                        return $this->failData('游戏已结束');
                    }
                } catch (\Exception $ex) {
                    $m->rollback();
                    return $this->failData($ex->getMessage());
                }
            }
            $game['end_game_time'] = date('Y/m/d H:i:s', $end_game_time);
            return $this->successData($game);
        }
        //如果有已结束的 查询已结束的游戏与 开奖结果
        $m = new \addons\fomo\model\Game();
        $end_game = $m->getLastEndGame();
        if(!empty($end_game)){
            $game = $end_game[0];
            $recordM = new \addons\fomo\model\RewardRecord();
            $last_winner = $recordM->getGameWinner($game['id']);
            $game['last_winner'] = $last_winner;
            return $this->successData($game);
        }else{
            return $this->failData('等待开启新一轮');
        }
        
    }
    
    public function getTeamTotal(){
        $game_id = $this->_get('game_id');
        $m = new \addons\fomo\model\TeamTotal();
        $data = $m->getTotalByGameId($game_id);
        return $this->successData($data);
    }
    
    public function getPrice(){
        $game_id = $this->_get('game_id');
        $m = new \addons\fomo\model\KeyPrice();
        $data = $m->getGameCurrentPrice($game_id);
        return $this->successData($data);

    }
    
    
    public function getKeys(){
        $game_id = $this->_get('game_id');
        $coin_id = $this->_get('coin_id');
        if($this->user_id <= 0){
            return $this->failData('未登录');
        }
        $keyRecordM = new \addons\fomo\model\KeyRecord();
        $key_num = $keyRecordM->getTotalByGameID($this->user_id, $game_id);
        $data['key_num'] = $key_num;
        $rewardM = new \addons\fomo\model\RewardRecord();
        $data['current_game_total_reward'] = $rewardM->getUserTotal($this->user_id,$coin_id,$game_id);
        return $this->successData($data);
    }



    
    
}
