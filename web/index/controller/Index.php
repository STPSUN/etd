<?php

namespace web\index\controller;

/**
 * 前端首页控制器
 */
class Index extends Base {
    
    //定位到fomo游戏界面
    public function index(){
        $inviter_address = $this->_get('inv');
        $this->inviter_address = $inviter_address;//设置邀请者地址缓存
        return redirect(getUrl('key_game/index','','fomo',false));
    }
    
}
