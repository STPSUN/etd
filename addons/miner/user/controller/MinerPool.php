<?php

namespace addons\miner\user\controller;

/**
 * Description of MinerPool
 * 矿场配置
 * @author shilinqing
 */
class MinerPool extends \web\user\controller\AddonUserBase {

    public function index() {
        if (IS_POST) {
            $tmp['id'] = 1;
            $tmp['parameter_val'] = input('parameter_val');
            $data[] = $tmp;
            $tmp['id'] = 2;
            $tmp['parameter_val'] = input('pic');
            $data[] = $tmp;
            $m = new \addons\miner\model\MinerPool();
            $res = $m->saveAll($data);
            if ($res)
                return $this->successData();
            else
                return $this->failData();
        } else {
            $this->assign('id', 1);
            $this->setLoadDataAction('loadData');
            return $this->fetch();
        }
    }

    /**
     * 加载数据
     */
    public function loadData() {
        $data = (new \addons\miner\model\MinerPool())->select();
        $info['parameter_val'] = $data[0]['parameter_val'];
        $info['pic'] = $data[1]['parameter_val'];
        return $info;
    }

}
