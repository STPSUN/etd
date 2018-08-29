<?php

namespace addons\miner\user\controller;

/**
 * Description of MinerConfig
 * 矿机设置
 * @author zmh
 */
class MinerConfig extends \web\user\controller\AddonUserBase {

    public function index() {
        return $this->fetch();
    }

    public function edit() {
        $m = new \addons\miner\model\MinerConfig();
        if (IS_POST) {
            $data = $_POST;
            $id = $data['id'];
            try {
                if (empty($id))
                    $m->add($data);
                else
                    $m->save($data);
                return $this->successData();
            } catch (\Exception $ex) {
                return $this->failData($ex->getMessage());
            }
        } else {
            $this->assign('id', $this->_get('id'));
            $this->setLoadDataAction('loadData');
            return $this->fetch();
        }
    }

    public function change_default() {
        $id = $this->_post('id');
        $m = new \addons\miner\model\MinerConfig();
        $status = $this->_post('status');
        $status = $status ? 1 : 0;
        try {
            $ret = $m->changeDefault($id, $status);
            if ($ret > 0) {
                return $this->successData();
            } else {
                $message = '操作失败';
                return $this->failData($message);
            }
        } catch (\Exception $ex) {
            return $this->failData($ex->getMessage());
        }
    }

    /**
     * 逻辑删除
     * @return type
     */
    public function del() {
        $id = $this->_post('id');
        $m = new \addons\miner\model\MinerConfig();
        try {
            $ret = $m->deleteData($id);
            if ($ret > 0) {
                return $this->successData();
            } else {
                $message = '删除失败';
                return $this->failData($message);
            }
        } catch (\Exception $ex) {
            return $this->failData($ex->getMessage());
        }
    }

    public function loadData() {
        $id = $this->_get('id');
        $m = new \addons\miner\model\MinerConfig();
        $data = $m->getDetail($id);
        return $data;
    }

    public function loadList() {
        $keyword = $this->_get('keyword');
        $filter = '';
        if ($keyword != null) {
            $filter .= ' name like \'%' . $keyword . '%\'';
        }
        $m = new \addons\miner\model\MinerConfig();
        $total = $m->getTotal($filter);
        $rows = $m->getDataList($this->getPageIndex(), $this->getPageSize(), $filter);
        return $this->toDataGrid($total, $rows);
    }

}
