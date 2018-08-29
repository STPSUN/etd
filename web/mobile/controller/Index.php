<?php

namespace web\mobile\controller;

/**
 * 前端首页控制器
 */
class Index extends Base {

    public function index(){
        return $this->fetch();
    }

}
