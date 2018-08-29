<?php

namespace addons\member\index\controller;

/**
 * 用户注册
 */
class Register extends \web\index\controller\AddonIndexBase {
    
    private $_address = array(); //eth接口返回
    private $ethPass = '';
    
    /**
     * 支持事务
     * @return type
     */
    public function index(){
        if(IS_POST){
//            return $this->successData();
            $data['phone'] = $this->_post('phone');
            $data['verify_code'] = $this->_post('verify_code');
            $password = $this->_post('password');
            $password1 = $this->_post('password1');
            $data['username'] = $this->_post('username');
            if($password != $password1){
                return $this->failData('两次输入的密码不一致');
            }
            if(strlen($password) < 8){
                return $this->failData('密码长度不能小于8');
            }
            $data['password'] = md5($password);
            $m = new \addons\member\model\MemberAccountModel();
            $count = $m->hasRegsterPhone($data['phone']);
            if($count > 0){
                 return $this->failData('此手机号已被注册,请直接登录或尝试找回密码');
            }
            $m->startTrans();
            try{
                $verifyM = new \addons\member\model\VericodeModel();
                $_verify = $verifyM->VerifyCode($data['verify_code'],$data['phone']);
                if(!empty($_verify)){
                    if(!empty($this->inviter_address)){
                        //获取邀请者id
                        $invite_user_id = $m->getUserByAddress($this->inviter_address);
                        $data['pid'] = $invite_user_id; //邀请者id
                    }
                    $data['register_time'] = NOW_DATETIME;
                    $res = $this->getEthAddr($data['phone']);
                    if($res){
                        $data['address'] = $this->_address; //eth地址
                        $data['eth_pass'] = $this->ethPass;
                        $user_id = $m->add($data); //用户id
                        $m->commit();
                        return $this->successData();
                    }
                }else{
                    $m->rollback();
                    return $this->failData('验证码失效,请重新注册');
                }
            } catch (\Exception $ex) {
                return $this->failData($ex->getMessage());
            }
            
        }else{
            $this->assign('time', 60*3);//验证码过期时间 秒
            $this->assign('id','');
            $this->assign('inviter_address',$this->inviter_address);
            $this->setLoadDataAction('');
            return $this->fetch();
        }
    }
    
    private function getEthAddr($name){
        $eth_pass = 'token'.$name.rand(0000,9999);
        $this->ethPass = $eth_pass;
        $res = $this->jsonrpc('personal_newAccount', [$eth_pass]);
        return $res;
    }

    private function jsonrpc($method, $params) {
        $m = new \web\common\model\sys\SysParameterModel();
        $port = $m->getValByName('port');
        $url = "http://127.0.0.1:".$port;
        $request = array('method' => $method, 'params' => $params, 'id' => 1);
        $request = json_encode($request);
        $opts = array('http' => array(
                'method' => 'POST',
                'header' => 'Content-type: application/json',
                'content' => $request
        ));
        $context = stream_context_create($opts);
        if ($result = file_get_contents($url, false, $context)) {
            $data = json_decode($result, true);
            if(!empty($data) && $data['result'] ){
                $this->_address = $data['result'];
                return true;
            }else{
                return false;
            }
        } else {
            return false;
        }
    }
  


    /**
     * 验证手机是否已经注册
     */
    public function hasReg($phone){
       $m = new \addons\member\model\MemberAccountModel();
       $count = $m->hasRegsterPhone($phone);
       return $this->successJSON($count);
    }
    
    /**
     * 短信验证
     */
    public function sms(){
        $phone = $this->_post('phone');
        $time = $this->_post('time');
        $type = $this->_post('type');
        if(empty($type))
            $type = 1;//注册验证码
        $m = new \addons\member\model\VericodeModel();
        $unpass_code = $m->hasUnpassCode($phone,$type);
        if(!empty($unpass_code)){
            return $this->failData('验证码未过期,请输入之前收到的验证码');
        }
        try{
            //发送验证码
            $res = \addons\member\utils\Sms::send($phone);
//            $res['success'] = true;
//            $res['message'] = '短信发送成功';
//            $res['code'] = '1111';
            if(!empty($res['code'])){
                //保存验证码
                $pass_time = date('Y-m-d H:i:s',strtotime("+".$time." seconds"));
                $data['phone'] = $phone;
                $data['code'] = $res['code'];
                $data['type'] = $type;
                $data['pass_time'] = $pass_time; //过期时间
                $m->add($data);
                unset($res['code']);
            }
            return $res;
        } catch (\Exception $ex) {
            return $this->failData($ex->getMessage());
        }
    }
    
}
    