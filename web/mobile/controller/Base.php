<?php

namespace web\mobile\controller;

/**
 * index base控制器
 */
class Base extends \web\common\controller\BaseController {
    
    protected $addon = '';
    protected $module = '';
    protected $controller = '';
    protected $base_view_path = '';
    protected $inviter_address = '';
    protected $user_id = -1;
    protected $username = '';
    protected $address = '';


    protected function _initialize() {
        $memberData = session('memberData');
        if (!empty($memberData)) {
            $this->user_id = $memberData['user_id'];
            $this->username = $memberData['username'];
            $this->address = $memberData['address'];
        }
        parent::_initialize();
        if (!IS_AJAX || IS_PJAX) {
            $this->base_view_path = $this->view_path;
            $addon = '';
            if (defined('ADDON_NAME'))
                $addon = ADDON_NAME;
            $__c = explode('.', CONTROLLER_NAME);
            if (count($__c) > 1)
                $controller = $__c[1];
            else
                $controller = $__c[0];
            $__controller = \think\Loader::parseName($controller);
            $this->addon = $addon;
            $this->module = MODULE_NAME;
            $this->controller = $__controller;
            $this->assign('_CONTROLLER_NAME', $__controller);
            $this->assign('_ADDON_NAME', $addon);
            $templateConfig = config('template');
            $suffix = ltrim($templateConfig['view_suffix'], '.');
            $this->assign('PUBLIC_HEADER', APP_PATH . MODULE_NAME . DS . 'view' . DS . 'default' . DS . 'public' . DS . 'header' . '.' . $suffix);
//            $this->assign('PUBLIC_FOOTER', APP_PATH . MODULE_NAME . DS . 'view' . DS . 'default' . DS . 'public' . DS . 'footer' . '.' . $suffix);
            $this->assign('username', $this->username);
            $this->assign('address', $this->address);
            $this->assign('user_id', $this->user_id);

        }
    }
    
    
    /**
     * 加载表单的方法名称
     * @param type $value
     */
    protected function setLoadDataAction($value) {
        $this->assign('loadDataAction', $value);
    }

    /**
     * 获取当前页码
     * @return type
     */
    protected function getPageIndex() {
        $pageIndex = $this->_get('page');
        if (empty($pageIndex))
            $pageIndex = 1;
        return $pageIndex;
    }

    /**
     * 获取每页显示数量
     * @return type
     */
    protected function getPageSize() {
        $pageSize = $this->_get('rows');
        if (empty($pageSize))
            $pageSize = 10;
        if ($pageSize > 50)
            $pageSize = 50;
        return $pageSize;
    }

    /**
     * 获取排序信息
     * @param type $orderBy 默认排序信息
     * @return string
     */
    protected function getOrderBy($orderBy) {
        $sort = $this->_get('sort');
        $order = $this->_get('order');
        if (!empty($sort) && !empty($order)) {
            $sortArr = explode(',', $sort);
            $orderArr = explode(',', $order);
            $i = 0;
            $s = '';
            foreach ($sortArr as $field) {
                if ($i > 0)
                    $s .= ',';
                $s .= $field . ' ' . $orderArr[$i];
                $i++;
            }
            $orderBy = $s;
        }
        return $orderBy;
    }

  
    /**
     * 返回DataGrid数据
     * @param type $total
     * @param type $rows     
     */
    protected function toDataGrid($total, $rows) {
        if (empty($rows))
            $rows = array();
        $data = array('total' => $total, 'rows' => $rows);
        return json($data);
    }
    
        /**
     * 输出错误JSON信息。
     * @param type $message     
     */
    protected function failJSON($message) {
        $jsonData = array('success' => false, 'message' => $message);        
        $json = json_encode($jsonData, true);
        echo $json;
        exit;
    }

    /**
     * 输出成功JSON信息
     * @param type $data
     */
    protected function successJSON($data = NULL) {
        $json = json_encode(array('success' => true, 'data' => $data), true);
        echo $json;
        exit;
    }

    /**
     * 外网转入记录获取。
     * @return type
     */
    protected function getEthOrders($user_id) {
        set_time_limit(200);
        $ethApi = new \EthApi();
        $userM = new \addons\member\model\MemberAccountModel();
        $eth_address = $userM->getUserAddress($user_id);
        if (!$eth_address)
            return false;

        $coinM = new \addons\config\model\Coins();
        $coins = $coinM->select();
        foreach ($coins as $coin) {
            $ethApi->set_byte($coin['byte']);
            if (!empty($coin['contract_address'])) {
                $ethApi->set_contract($coin['contract_address']);
            }
            $transaction_list = $ethApi->erscan_order($eth_address, $coin['is_token']);
            if (empty($transaction_list)) {
                continue;
            }
            $res = $this->checkOrder($user_id, $eth_address, $coin['id'], $transaction_list);
        }
        return true;
    }

    /**
     * 外网数据写入
     * @param type $user_id 用户id
     * @param type $address 用户地址
     * @param type $list    抓取到的数据
     * @param type $coin_id 币种id
     * @return boolean
     */
    private function checkOrder($user_id, $address, $coin_id, $list) {
        $m = new \addons\eth\model\EthTradingOrder();
        $balanceM = new \addons\member\model\Balance();
        $recordM = new \addons\member\model\TradingRecord();
        foreach ($list as $val) {
            $txhash = $val['hash'];
            $block_number = $val['block_number'];
            $from_address = $val['from'];
            try {
                $res = $m->getDetailByTxHash($txhash); //订单匹配
                if ($res) {
                    return true;
                }
                $m->startTrans();
                $amount = $val['amount'];
                $eth_order_id = $m->transactionIn($user_id, $from_address, $address, $coin_id, $amount, $txhash, $block_number, 0, 1, 1, "外网转入");
                if ($eth_order_id > 0) {
                    //插入转入eth记录成功
                    $balance = $balanceM->updateBalance($user_id, $amount, $coin_id, true);

                    if (!$balance) {
                        $m->rollback();
                        return false;
                    }

                    $type = 2;
                    $before_amount = $balance['before_amount'];
                    $after_amount = $balance['amount'];
                    $change_type = 1; //减少
                    $remark = '外网转入';
                    $r_id = $recordM->addRecord($user_id, $coin_id, $amount, $before_amount, $after_amount, $type, $change_type, $user_id, $address, '', $remark);
                    if (!$r_id) {
                        $m->rollback();
                        return false;
                    }
                    $m->commit();
                    return true;
                } else {
                    $m->rollback();
                    return false;
                }
            } catch (\Exception $ex) {
                return false;
            }
        }
        return true;
    }

    protected function countRate($total_price, $rate){
        return $total_price * $rate / 100;
    }
    
}
