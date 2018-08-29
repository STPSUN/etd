<?php 

namespace web\api\controller;

class Market extends \web\api\controller\ApiBase{
    
    /**
     * 更新行情列表 BTC/USDT
     */
    public function updateTicketList(){
        try{
            $hot_api = new \web\common\utils\HotApi();
            $m = new \web\api\model\MarketModel();
            $list = $m->getDataList(-1,-1,'is_allow=1');
//            $anyRate = $this->convertCurrency("USD", "CNY", "1");
//            dump($anyRate);exit;
            $anyRate = 6.5;
            foreach($list as $k => $data){
                $symbol = strtolower(str_replace("/","",$data['transfer_name']));
                $detail_merged = $hot_api->get_detail_merged($symbol);
                if(!empty($detail_merged)){
                    $tick = $detail_merged['tick'];
                    $tick['id'] = $data['id'];
                    $tick['transfer_name'] = $data['transfer_name'];
                    $tick['coin_name'] = $data['coin_name'];
                    $tick['pay_coin_name'] = $data['pay_coin_name'];
                    $tick['rate'] = ($tick['close'] - $tick['open']) / $tick['open'];
                    $tick['cny'] = $anyRate * $tick['close'];
                    $tick['update_time'] = NOW_DATETIME;
                    $m->save($tick);   
                }
            }
            return $this->successJSON();
            
        } catch (\Exception $ex) {
            return $this->failJSON($ex->getMessage());
        }
        
    }
    
    /**
     * 获取行情列表
     */
    public function getTicketList(){
        try{
            $order = 'id';
            $m = new \web\api\model\MarketModel();
            $fields = 'coin_name,pay_coin_name,close,cny,rate';
            $list = $m->getDataList(-1,-1,'is_allow=1',$fields,$order);
            return $this->successJSON($list);
            
        } catch (\Exception $ex) {
            return $this->failJSON($ex->getMessage());
        }
        
    }
    
    public function getCoinDetail(){
        $coin_name = $this->_get('coin_name');
        if(empty($coin_name)){
            return $this->failJSON('缺少参数');
        }
        $m = new \web\api\model\MarketModel();
        $fields = 'transfer_name,coin_name,amount,close,high,low,cny,rate';
        $where['coin_name'] = $coin_name;
        $where['is_allow'] = 1;
        $data = $m->where($where)->field($fields)->find();
        return $this->successJSON($data);
    }
    
    /**
     * 获取两个币种的汇率
     * @param type $from
     * @param type $to
     * @param type $amount
     * @return type
     */
    private function convertCurrency($from, $to, $amount){
        $url = "https://forex.1forge.com/1.0.3/quotes?pairs=CNYUSD";
        $data = \web\common\utils\HttpUtil::httpGet($url);
        preg_match("/<div>1\D*=(\d*\.\d*)\D*<\/div>/",$data, $converted);
        $converted = preg_replace("/[^0-9.]/", "", $converted[1]);
        return number_format(round($converted, 3), 1);

    }
}