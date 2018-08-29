<?php

namespace web\api\controller;

class Transaction extends ApiBase{
    
    /**
     * 交易
     * 获取币种列表
     * @return type
     */
    public function getCoinList(){
        $m = new \addons\candy\model\CandyConfigModel();
        $list = $m->getDataList($this->getPageIndex(),$this->getPageSize(),'','id,candy_name,max_price','id asc');
        return $this->successJSON($list);
    }
    
    /**
     * 获取交易收取手续费比率
     */
    public function getTaxRate(){
        $m = new \web\common\model\sys\SysParameterModel();
        $is_tax = $m->getValByName('is_tax');
        if($is_tax == 1){
            $data = $m->getValByName('tax_rate');
        }else{
            $data = 0;
        }
        return $this->successJSON($data);
    }
    
    /**
     * 列表数据
     */
    public function index(){
        $user_id = $this->_get('user_id');
        $type = $this->_get('type'); //0=卖出,1=买入,2=卖出订单,3=买入订单
        $coin_id = $this->_get('coin_id');
        if(empty($user_id) || ($type < 0) || empty($coin_id) ){
            return $this->failJSON('missing arguments');
        }
        if($type > 3){
            return $this->failJSON('加载列表类型错误');
        }
        $m = new \addons\otc\model\OtcOrder();
        $filter = 'coin_id='.$coin_id;
        $orderby = 'price asc';
        $fields = 'id,username,buy_username,pay_type,type,price,amount,total_amount,pay_amount,status,add_time';
        try{
            switch ($type){
                case 0:
                case 1:
                    $filter .= ' and type='.$type.' and status=0 and user_id!='.$user_id;
                break;
                case 2:
                    $filter .= ' and user_id='.$user_id;
                    $orderby = 'status asc';
                break;
                case 3:
                    $filter .= ' and buy_user_id='.$user_id;
                    $orderby = 'status desc';
                break;
            }
            $list = $m->getList($this->getPageIndex(),$this->getPageSize(),$filter,$fields,$orderby);
            return $this->successJSON($list);
        } catch (\Exception $ex) {
            return $this->failJSON($ex->getMessage());
        }
    }
    
    /**
     * 获取订单详情 
     */
    public function getOrderDetail(){
        $user_id = $this->_post('user_id');
        $order_id = $this->_post('order_id');
        if(empty($user_id) || empty($order_id))
            return $this->failJSON('missing arguments');
        $m = new \addons\otc\model\OtcOrder();
        try{
            $data = $m->getOrderDetail($order_id);
            return $this->successJSON($data);
        } catch (\Exception $ex) {
            return $this->failJSON($ex->getMessage());
        }
    }
    
    /**
     * 提交收款方式
     * @return type
     */
    public function setPayConfig(){
        if(!IS_POST){
            return $this->failJSON('illegal request');
        }
        $user_id = $this->_post('user_id');
        $type = $this->_post('type');
        $account = $this->_post('account');
        $id = $this->_post('id');
        $name = $this->_post('name');
        $bank_address = $this->_post('bank_address');
        if(empty($user_id) || empty($type) || empty($account)){
            return $this->failJSON('missing arguments');
        }
        if($type == 3){
            if(empty($name) || empty($bank_address)){
                return $this->failJSON('姓名与开户行地址不能为空');
            }
            $data['bank_address'] = $bank_address;
            $data['name'] = $name;
        }
        try{
            $m = new \addons\otc\model\PayConfig();
            if(!empty($id)){
                $where['user_id'] = $user_id;
                $where['id'] = $id;
                $data['type'] = $type;
                $data['account'] = $account;
                $data['update_time'] = NOW_DATETIME;
                $ret = $m->where($where)->update($data);
            }else{
                $data['user_id'] = $user_id;
                $data['account'] = $account;
                $data['type'] = $type;
                $data['update_time'] = NOW_DATETIME;
                $ret = $m->add($data);
            }
            if($ret > 0){
                return $this->successJSON();
            }else{
                return $this->failJSON('添加或更新数据失败');
            }
        } catch (\Exception $ex) {
            return $this->failJSON($ex->getMessage());
        }
    }
    
    /**
     * 获取收款方式
     * @return type
     */
    public function getPayConfig(){
        $user_id = $this->_post('user_id');
        $type = $this->_post('type');
        if(empty($user_id) || empty($type)){
            return $this->failJSON('missing arguments');
        }
        try{
            $m = new \addons\otc\model\PayConfig();
            $data = $m->getUserPayDetail($user_id,$type);
            return $this->successJSON($data);
        } catch (\Exception $ex) {
            return $this->failJSON($ex->getMessage());
        }
        
    }
    
    /**
     * 提交otc交易订单
     * 挂卖 需要选择收款方式,买方需要上传支付凭证
     */
    public function postSaleOrder(){
        if(!IS_POST)
            return $this->failJSON('illegal request');
        
        $user_id = intval($this->_post('user_id'));
        $pay_password = $this->_post('pay_password');
        $price = $this->_post('price');
        $num = $this->_post('num');
        $candy_id = $this->_post('candy_id'); //币种id
        $type = 0; //交易类型，0=卖出，1=买入
        $remark = $this->_post('remark');//备注
        if(empty($user_id) || empty($pay_password) || empty($price) || empty($num) || empty($candy_id)){
            return $this->failJSON('missing arguments');
        }
        if($num < 1){
            return $this->failJSON('数量不能小于1');
        }
        $pay_password = md5($pay_password);
        $userM = new \addons\member\model\MemberAccountModel();
        $user = $userM->getDetail($user_id);
        if($user['pay_password'] != $pay_password){
            return $this->failJSON('支付密码错误');
        }
        if($user['is_auth'] != 1 ){
            return $this->failJSON('认证未通过');
        }
        if($user['is_frozen'] == 1){
            return $this->failJSON('账号已冻结,无法进行余额操作');
        }
        //卖出
        $pay_type = $this->_post('pay_type');
        if(empty($pay_type))
            return $this->failJSON('请选择收款方式');
        
        $payM = new \addons\otc\model\PayConfig();
        $pay_data = $payM->getDetailForJSON($user_id,$pay_type);
        if(empty($pay_data))
            return $this->failJSON('未设置收款方式');
        
        $pay_detail_json = json_encode($pay_data,JSON_UNESCAPED_UNICODE);
        $paramM = new \web\common\model\sys\SysParameterModel();
        $is_tax = $paramM->getValByName('is_tax');
        $tax_num = 0; //手续费金额 下单方出
        if($is_tax == 1){
            $tax_rate = $paramM->getValByName('tax_rate'); //手续费比率
            $tax_num = $tax_rate * $num / 100;
        }
        $total_num = $num + $tax_num; //总数量=手续费数量+卖出数量
        $_verify = $this->_tradingVerify($candy_id, $price);
        if(!$_verify['success']){
            return $this->failJSON($_verify['msg']);
        }
        try{
            $balanceM = new \addons\member\model\AssetModel();
            $balanceM->startTrans();
            $balance = $balanceM->verifyStock($user_id, $candy_id,$total_num);
            if(empty($balance)){
                return $this->failJSON('余额不足');
            }
            $pay_amount = $num * $price; //需支付金额
            $m = new \addons\otc\model\OtcOrder();
            $id = $m->addOrder($user_id, $candy_id, $type, $num, $tax_num, $total_num, $price, $pay_amount, $pay_type, $pay_detail_json,$remark);
            if($id > 0){
                $before_num = $balance['num'];
                $balance['before_num'] = $before_num;
                $balance['num'] = $before_num - $total_num;
                $balance['otc_frozen_num'] = $balance['otc_frozen_num'] + $total_num;
                $balance['update_time'] = NOW_DATETIME;
                $is_save = $balanceM->save($balance);
                if($is_save > 0){
                    $balanceM->commit();
                    return $this->successJSON();
                }else{
                    $balanceM->rollback();
                    return $this->failJSON('余额更新失败');
                }
                
            }else{
                $balanceM->rollback();
                return $this->failJSON('订单提交失败');
            }
        } catch (\Exception $ex) {
            $balanceM->rollback();
            return $this->failJSON($ex->getMessage());
        }

    }
    
    /**
     * otc提交验证 - 1. 是否超出最高价 
     * @param type $user_id
     * @param type $total_num
     * @param type $candy_id
     */
    public function _tradingVerify($candy_id, $price){
        $ret = array(
            'success' => true,
            'msg' => ''
        );
        $candyM = new \addons\candy\model\CandyConfigModel();
        $candy = $candyM->getDetail($candy_id);
        if(!empty($candy)){
            if($candy['max_price'] < $price){
                $ret['success'] = false;
                $ret['msg'] = '超出当前最高价限制';  
            }
        }else{
            $ret['success'] = false;
            $ret['msg'] = '所选币种不存在';
        }
        return $ret;
        
    }
    
    /**
     * 挂买订单
     * 挂买 检测卖方余额是否符合买入数量要求, 卖方需要提供收款方式,买方需上传凭证
     * 挂买 total_num = num , 成交后 扣除 手续费的数量 = 得到的数量
     */
    public function postBuyOrder(){
        if(!IS_POST)
            return $this->failJSON('illegal request');
        
        $user_id = intval($this->_post('user_id'));
        $pay_password = $this->_post('pay_password');
        $price = $this->_post('price');
        $num = $this->_post('num');
        $candy_id = $this->_post('candy_id'); //币种id
        $type = 1; //交易类型，0=卖出，1=买入
        $remark = $this->_post('remark');//备注
        if(empty($user_id) || empty($pay_password) || empty($price) || empty($num) || empty($candy_id)){
            return $this->failJSON('missing arguments');
        }
        if($num < 1){
            return $this->failJSON('数量不能小于1');
        }
        $pay_password = md5($pay_password);
        $userM = new \addons\member\model\MemberAccountModel();
        $user = $userM->getDetail($user_id);
        if($user['pay_password'] != $pay_password){
            return $this->failJSON('支付密码错误');
        }
        if($user['is_auth'] != 1 ){
            return $this->failJSON('认证未通过');
        }
        if($user['is_frozen'] == 1){
            return $this->failJSON('账号已冻结,无法进行余额操作');
        }
        $paramM = new \web\common\model\sys\SysParameterModel();
        $is_tax = $paramM->getValByName('is_tax');
        $tax_num = 0; //手续费数量 下单方出
        if($is_tax == 1){
            $tax_rate = $paramM->getValByName('tax_rate'); //手续费比率
            $tax_num = $tax_rate * $num / 100;
        }
        $total_num = $num;
        $pay_amount = $num * $price; //需支付金额
        try{
            $m = new \addons\otc\model\OtcOrder();
            $id = $m->addOrder($user_id, $candy_id, $type, $num, $tax_num, $total_num, $price, $pay_amount,0, '', $remark);
            if($id > 0){
                return $this->successJSON();
            }else{
                return $this->failJSON('订单提交失败');
            }
            
        } catch (\Exception $ex) {
            return $this->failJSON($ex->getMessage());
        }
    }
    
    
    /**
     * 下单
     */
    public function placeOrder(){
        if(!IS_POST)
            return $this->failJSON('illegal request');
        $order_id = $this->_post('order_id');
        $user_id = $this->_post('user_id'); //下单用户id
        if(empty($user_id) || empty($order_id)){
            return $this->failJSON('missing arguments');
        }
        $m = new \addons\otc\model\OtcOrder();
        $order = $m->getOrderByStatus($order_id);
        $userM = new \addons\member\model\MemberAccountModel();
        $user = $userM->getDetail($user_id);
        if($user['is_auth'] != 1){
            return $this->failJSON('认证未通过');
        }
        if($user['is_frozen'] == 1){
            return $this->failJSON('账号已冻结,无法进行余额操作');
        }
        try{
            if(!empty($order)){
                if($order['user_id'] == $user_id){
                    return $this->failJSON('无法对自己的订单进行操作');
                }
                $m->startTrans();
                if($order['type'] == 1){
                    //买单 验证是否有足够数量卖出, 需填写收款方式
                    $pay_type = $this->_post('pay_type');
                    if(empty($pay_type)){
                        $m->rollback();
                        return $this->failJSON('请选择收款方式');
                    }
                    $order['pay_type'] = $pay_type;
                    $payM = new \addons\otc\model\PayConfig();
                    $pay_data = $payM->getDetailForJSON($user_id,$pay_type);
                    if(empty($pay_data)){
                        $m->rollback();
                        return $this->failJSON('未设置收款方式');
                    }
                    $order['pay_detail_json'] = json_encode($pay_data,JSON_UNESCAPED_UNICODE);

                    $candy_id = $order['candy_id']; //币种
                    $total_num = $order['total_num']; //交易数量
                    $balanceM = new \addons\member\model\AssetModel();
                    $balance = $balanceM->verifyStock($user_id, $candy_id, $total_num);
                    if(empty($balance)){
                        $m->rollback();
                        return $this->failJSON('您的余额不满足此订单,无法下单');

                    }
                    //冻结卖出用户余额
                    $before_num = $balance['num'];
                    $balance['before_num'] = $before_num;
                    $balance['num'] = $balance['num'] - $total_num;
                    $balance['otc_frozen_num'] = $balance['otc_frozen_num'] + $total_num;
                    $balance['update_time'] = NOW_DATETIME;
                    $ret = $balanceM->save($balance);
                    if($ret <= 0){
                        return $this->failJSON('更新余额失败');
                    }
                }
                $order['buy_user_id'] = $user_id;
                $order['status'] = 2;
                $order['deal_time'] = NOW_DATETIME;
                $order['update_time'] = NOW_DATETIME;
                $res = $m->save($order,'',null,false);
                if($res > 0){
                    $m->commit();
                    return $this->successJSON();

                }else{
                    $m->rollback();
                    return $this->failJSON('下单失败');

                }
            }else{
                return $this->failJSON('订单不存在');
            }
        } catch (\Exception $ex) {
            $m->rollback();
            return $this->failJSON($ex->getMessage());
        }
    }
    
    
    /**
     * 上传凭证
     */
    public function uploadProof(){
        if(!IS_POST)
            return $this->failJSON('illegal request');
        $user_id = $this->_post('user_id');
        $order_id = $this->_post('order_id');
        if(empty($user_id) || empty($order_id))
            return $this->failJSON('missing arguments');
        try{
            $m = new \addons\otc\model\OtcOrder();
            $order = $m->getOrderByStatus($order_id,2);
            if(empty($order)){
               return $this->failJSON('订单不存在'); 
            }
            $type = $order['type'];//交易类型，0=卖出，1=买入
            $base64 = $this->_post('file');
            $savePath = 'transaction/proof/'.$user_id.'/';
            $data = $this->base_img_upload($base64, $user_id, $savePath);
            if($data['success']){
                //保存用户头像地址 return $res['path']
                $res = $m->setProofPic($order_id, $user_id, $type, $data['path']);
                if($res > 0){
                    return $this->successJSON($data);
                }else{
                    return $this->failJSON('上传凭证失败');
                }
            }else{
                return $this->failJSON($data['message']);
            }
        } catch (\Exception $ex) {
            return $this->failJSON($ex->getMessage());
        }
    }
    
    /**
     * 状态为3 = 待确定  可以确认完成
     * 余额处理, 添加记录 ,变更订单状态
     */
    public function confirm(){
        if(!IS_POST)
            return $this->failJSON('illegal request');
        $user_id = $this->_post('user_id');
        $order_id = $this->_post('order_id');
        if(empty($user_id) || empty($order_id))
            return $this->failJSON('missing arguments');
        
        $m = new \addons\otc\model\OtcOrder();
        $order = $m->getOrderByStatus($order_id,3);
        if(empty($order)){
            return $this->failJSON('订单不存在');
        }
        try{
            $type = $order['type'];
            if($type == 1 && $order['buy_user_id'] != $user_id){
                //买单 buy_user_id 可以确认完成
                return $this->failJSON('确认完成操作失败');
            }else if($type == 0 && $order['user_id'] != $user_id){
                return $this->failJSON('确认完成操作失败');
            }
            $balanceM = new \addons\member\model\AssetModel();
            $ret = $balanceM->otcTradingConfirm($order_id);
            if($ret){
                if($type == 1){
                    $user_id = $order['user_id'];
                }else{
                    $user_id = $order['buy_user_id'];
                }
                $userM = new \addons\member\model\MemberAccountModel();
                $user = $userM->getDetail($user_id);
                $phone = $user['phone'];
                $res = \addons\member\utils\Sms::send($phone,2);
                if(!$res['success']){
                    return $this->failJSON('短信通知发送失败:'.$res['message']);
                }
                return $this->successJSON();
            }else{
                return $this->failJSON('确认订单失败');
            }
            
        } catch (\Exception $ex) {
            return $this->failJSON($ex->getMessage());
        }
        
    
    }
    
    /**
     * 取消交易(用户购买的委托)
     * 状态必须为 已匹配 status=2
     * 如果是卖方:需要解冻金额
     */
    public function cancel_trade(){
        if(!IS_POST)
            return $this->failJSON('illegal request');
        $user_id = $this->_post('user_id');
        $order_id = $this->_post('order_id');
        if(empty($user_id) || empty($order_id))
            return $this->failJSON('missing arguments');
        $m = new \addons\otc\model\OtcOrder();
        $order = $m->getOrderByStatus($order_id, 2);
        if(empty($order)){
            return $this->failJSON('订单不存在');
        }
        try{
            if($order['buy_user_id'] != $user_id){
                return $this->failJSON('下单用户数据错误,操作失败');
            }
            $m->startTrans();
            //清空 buy_user_id,pay_type,pay_detail_json
            $order['buy_user_id'] = 0;
            $order['status'] = 0;
            if($order['type'] == 1){
                $order['pay_type'] = 0;
                $order['pay_detail_json'] = '';
            }
            $res = $m->save($order);
            if($res > 0){
                if($order['type'] == 1){
                    //买单 退还冻结金额 
                    $candy_id = $order['candy_id'];
                    $total_num = $order['total_num'];
                    $balanceM = new \addons\member\model\AssetModel();
                    $balance = $balanceM->getUserAsset($user_id, $candy_id);
                    if(empty($balance)){
                        $m->rollback ();
                        return $this->failJSON ('指定余额不存在');
                    }
                    $before_num = $balance['num'];
                    $balance['before_num'] = $before_num;
                    $balance['num'] = $before_num + $total_num;
                    $balance['otc_frozen_num'] = $balance['otc_frozen_num'] - $total_num;
                    $balance['update_time'] = NOW_DATETIME;
                    $is_save = $balanceM->save($balance);
                    if(empty($is_save)){
                        $m->rollback ();
                        return $this->failJSON('更新余额失败');
                    }
                }
                $m->commit();
                return $this->successJSON();
            }else{
                $m->rollback();
                return $this->failJSON('取消交易失败');
            } 
            
        } catch (\Exception $ex) {
            return $this->failJSON($ex->getMessage());
        }
        
        
    }
    
    /**
     * 撤单(用户自身的委托)
     * status=0 可以撤单
     */
    public function cancel(){
        if(!IS_POST)
            return $this->failJSON('illegal request');
        $user_id = $this->_post('user_id');
        $order_id = $this->_post('order_id');
        if(empty($user_id) || empty($order_id))
            return $this->failJSON('missing arguments');
        $m = new \addons\otc\model\OtcOrder();
        $order = $m->getOrderWithUserID($order_id,$user_id);
        if(!empty($order)){
            $status = $order['status'];
            if($status == 0){
                $m->startTrans();
                unset($order['deal_time']);
                unset($order['pay_detail_json']);
                $order['status'] = -1; //撤单
                $order['update_time'] = NOW_DATETIME;
                $ret = $m->save($order);
                if($ret > 0){
                    $type = $order['type']; 
                    //卖单解除冻结数量
                    if($type == 0){
                        $candy_id = $order['candy_id'];
                        $total_num = $order['total_num'];
                        $balanceM = new \addons\member\model\AssetModel();
                        $balance = $balanceM->getUserAsset($user_id, $candy_id);
                        if(empty($balance)){
                            $m->rollback ();
                            return $this->failJSON ('指定余额不存在');
                        }
                        $before_num = $balance['num'];
                        $balance['before_num'] = $before_num;
                        $balance['num'] = $before_num + $total_num;
                        $balance['otc_frozen_num'] = $balance['otc_frozen_num'] - $total_num;
                        $balance['update_time'] = NOW_DATETIME;
                        $is_save = $balanceM->save($balance);
                        if(empty($is_save)){
                            $m->rollback ();
                            return $this->failJSON('更新余额失败');
                        }
                    }
                    $m->commit();
                    return $this->successJSON();
                }else{
                    $m->rollback ();
                    return $this->failJSON('撤单失败');
                }
            }else if($status == 1)
                return $this->failJSON ('订单已完成,无法撤单');
            else if($status == 2)
                return $this->failJSON ('已被下单,无法撤单');
            else if($status == 3)
                return $this->failJSON ('待确认订单,无法撤单');
            else if($status == -1)
                return $this->successJSON('已撤销订单');
        }else{
            return $this->failJSON('订单不存在');
        }
    }
    
    /**
     * 获取金币最高价与列表
     * @return type
     */
    public function getCandyList(){
        try{
            $m = new \addons\candy\model\CandyConfigModel();
            $data = $m->getCandyField();
            return $this->successJSON($data);

        } catch (\Exception $ex) {
            return $this->failJSON($ex->getMessage());
        }
    }
    
    /**
     * 获取手续费
     * @return type
     */
    public function getTradingTax(){
        try{
            $m = new \web\common\model\sys\SysParameterModel();
            $is_tax = $m->getValByName('is_tax');
            $trading_rate = 0;
            if($is_tax == 1){
                $trading_rate = $m->getValByName('tax_rate');
            }
            return $this->successJSON($trading_rate);
            
        } catch (\Exception $ex) {
            return $this->failJSON($ex->getMessage());
        }
    }
    
    /**
     * 保存base64 图片
     * @param type $base64
     * @param type $user_id
     * @return boolean|string
     */
    private function base_img_upload($base64 ,$user_id,$savePath){
        // 获取表单上传文件 例如上传了001.jpg
        if(empty($base64)){
           return false;
        }
        $_message = array(
          'success' =>false,
          'message' =>'',
        );
        $rootPath = UPLOADFOLDER;
        $uploadFolder = substr($rootPath, 1);
        $uploadPath = $uploadFolder . $savePath;
        $path = $_SERVER['DOCUMENT_ROOT'] . $uploadPath;
        $file_name= time(). getMD5Name(3,$user_id);
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64, $result)){
            $ext = array('jpg', 'gif', 'png', 'jpeg');
            $type = $result[2];
            if(!in_array($type, $ext)){
                $_message['message'] = '图片格式错误';
                return $_message;
            }
            $pic_path = $path. $file_name. "." .$type;
            $file_size = file_put_contents($pic_path, base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64)));
            if(!$pic_path || $file_size > 10 * 1024 * 1024){
                unlink($pic_path);
                $_message['message'] = '图片保存失败';
                return $_message;
            }

        }else{
            $_message['message'] = '图片格式编码错误';
            return $_message;
        }
        $_message['success'] = true;
        $_message['message'] = '上传成功';
        $_message['path'] = $uploadPath.$file_name.'.'.$type;
        return $_message;
    }
    
}
