<?php

namespace web\api\controller;

use think\Exception;

class Member extends \web\api\controller\ApiBase {

    private $_address = array(); //eth接口返回
    private $ethPass = '';

    /**
     * 获取用户资产
     */
    public function getUserAsset(){
        $user_id = intval($this->_get('user_id'));
        if(empty($user_id)){
            return $this->failJSON('missing arguments');
        }
        return json($this->service->getUserAsset($user_id));
        }

    /*
     * 获取用户数据
     */
    public function getUserInfo(){
        $user_id = intval($this->_get('user_id'));
        if(empty($user_id)){
            return $this->failJSON('missing arguments');
        }
        return json($this->service->getUserInfo($user_id));

        }

    /**
     * 用户登陆
     */
    public function login() {
        if (IS_POST) {
            try {
                $phone = $this->_post('phone');
                $password = $this->_post('password');
                if (empty($password)) {
                    return $this->failJSON('密码不能为空');
                }
                if (empty($phone)) {
                    return $this->failJSON('手机号不能为空');
                }
                $m = new \addons\member\model\MemberAccountModel();
                $res = $m->getLoginData($password, $phone, '', 'id,phone,address,username');
                if ($res) {
                    $memberData['username'] = $res['phone'];
                    $memberData['address'] = $res['address'];
                    $memberData['user_id'] = $res['id'];
                    session('memberData', $memberData);

                    $token = md5($res['id'] . $this->apikey);
                    $this->setGlobalCache($res['id'], $token); //user_id存储到入redis
                    $data['phone'] = $res['phone'];
                    $data['username'] = $res['username'];
                    $data['address'] = $res['address'];
                    $data['token'] = $token;
                    return $this->successJSON($data);
                } else {
                    return $this->failJSON('帐号或密码有误');
                }
            } catch (\Exception $ex) {
                return $this->failJSON($ex->getMessage());
            }
        } else {
            return $this->failJSON('请求出错');
        }
    }

    /*
     * 短信登录
     */

    public function smsLogin() {
        if (IS_POST) {
            try {
                $phone = $this->_post('phone');
                $verify_code = $this->_post('verify_code');
                $type = $this->_post('type', 3);
                if (empty($phone)) {
                    return $this->failJSON('手机号不能为空');
                }
                if (empty($verify_code)) {
                    return $this->failJSON('验证码不能为空');
                }

                $verifyM = new \addons\member\model\VericodeModel();
                $_verify = $verifyM->VerifyCode($verify_code, $phone, $type);
                if (!empty($_verify)) {
                    $m = new \addons\member\model\MemberAccountModel();
                    $res = $m->getLoginDataBySms($phone, 'id,phone,address'); //短信登录 根据手机号查找用户信息
                    if ($res) {
                        $memberData['username'] = $res['phone'];
                        $memberData['address'] = $res['address'];
                        $memberData['user_id'] = $res['id'];
                        session('memberData', $memberData);

                        $token = md5($res['id'] . $this->apikey);
                        $this->setGlobalCache($res['id'], $token); //user_id存储到入redis

                        $data['username'] = $res['phone'];
                        $data['address'] = $res['address'];
                        $data['token'] = $token;
                        return $this->successJSON($data);
                    } else {
                        $this->failJSON('该手机尚未注册');
                    }
                } else {
                    $this->failJSON('验证码已失效');
                }
            } catch (\Exception $ex) {
                return $this->failJSON($ex->getMessage());
            }
        } else {
            return $this->failJSON('请求出错');
        }
    }

    /**
     * 用户注册
     */
    public function register() {
        if (IS_POST) {
            $data['phone'] = $this->_post('phone');
            $data['verify_code'] = $this->_post('verify_code');
            $password = $this->_post('password');
            $password1 = $this->_post('password1');
            $data['username'] = $this->_post('username');
            if ($password != $password1) {
                return $this->failJSON('两次输入的密码不一致');
            }
            if (strlen($password) < 8) {
                return $this->failJSON('密码长度不能小于8');
            }
            $data['password'] = md5($password);
            $m = new \addons\member\model\MemberAccountModel();
            $count = $m->hasRegsterUsername($data['username']);
            if ($count > 0) {
                return $this->failJSON('此用户名已被注册,请直接登录或尝试找回密码');
            }
            $count = $m->hasRegsterPhone($data['phone']);
            if ($count > 0) {
                return $this->failJSON('此手机号已被注册,请直接登录或尝试找回密码');
            }
            $m->startTrans();
            try {
                $verifyM = new \addons\member\model\VericodeModel();
                $_verify = $verifyM->VerifyCode($data['verify_code'], $data['phone']);
//                if (!empty($_verify)) {
                    $inviter_address = $this->_post('inviter_address');
                    if (!empty($inviter_address)) {
                        //获取邀请者id
                        $invite_user_id = $m->getUserByUsername($inviter_address);
                        if (!empty($inviter_address)) {
                            $data['pid'] = $invite_user_id; //邀请者id
                        } else {
                            return $this->failJSON('邀请人不存在');
                        }
                    }
                    $data['register_time'] = NOW_DATETIME;
                    $res = $this->getEthAddr($data['phone']);
                    if ($res) {
                        $data['address'] = $this->_address; //eth地址
                        $data['eth_pass'] = $this->ethPass;
                        $user_id = $m->add($data); //用户id
                        $m->commit();
                        return $this->successJSON('注册成功');
                    }
//                } else {
//                    $m->rollback();
//                    return $this->failJSON('验证码失效,请重新注册');
//                }
            } catch (\Exception $ex) {
                return $this->failJSON($ex->getMessage());
            }
        } else {
            return $this->failJSON('请求出错');
        }
    }

    private function getEthAddr($name) {
        $eth_pass = 'token' . $name . rand(0000, 9999);
        $this->ethPass = $eth_pass;
        $res = $this->jsonrpc('personal_newAccount', [$eth_pass]);
        return $res;
    }

    private function jsonrpc($method, $params) {
        $m = new \web\common\model\sys\SysParameterModel();
        $port = $m->getValByName('port');
        $url = "http://127.0.0.1:" . $port;
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
            if (!empty($data) && $data['result']) {
                $this->_address = $data['result'];
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * 获取手机验证码
     */
    public function getPhoneVerify() {
        $phone = $this->_post('phone');
        $time = $this->_post('time');
        $type = $this->_post('type');
        if (empty($type))
            $type = 1; //注册验证码
        if ($type == 2) {
            //找回密码
            $memberM = new \addons\member\model\MemberAccountModel();
            $ret = $memberM->hasRegsterPhone($phone);
            if ($ret <= 0) {
                return $this->failJSON('手机号未注册 - Account is not exists');
            }
        }
        $m = new \addons\member\model\VericodeModel();
        $unpass_code = $m->hasUnpassCode($phone, $type);
        if (!empty($unpass_code)) {
            return $this->failJSON('验证码未过期,请输入之前收到的验证码');
        }
        try {
            //发送验证码 todo
            $res = \addons\member\utils\Sms::send($phone);
//            $res['success'] = true;
//            $res['message'] = '短信发送成功';
//            $res['code'] = '1111';
            if (!$res['success']) {
                return $this->failJSON($res['message']);
            }
            //保存验证码
            $pass_time = date('Y-m-d H:i:s',strtotime("+".$time." seconds"));
            $data['phone'] = $phone;
            $data['code'] = $res['code'];
            $data['type'] = $type;
            $data['pass_time'] = $pass_time; //过期时间
            $result = $m->add($data);
            if (empty($result)) {
                return $this->failJSON('验证码生成失败'); //写入数据库失败
            }
            unset($res['code']);

            return $this->successJSON($res['message']);
        } catch (\Exception $ex) {
            return $this->failJSON($ex->getMessage());
        }
    }

    public function getUserEthAddr() {
        $user_id = intval($this->_get('user_id'));
        if (empty($user_id)) {
            return $this->failJSON('missing arguments');
        }
//        return json($this->service->getUserEthAddr($user_id));
        return;
    }

    public function editUserAddr() {
        if (!IS_POST) {
            return $this->failJSON('使用POST提交');
        }
        $user_id = $this->_post("user_id/d");
        return json($this->service->editUserAddr($user_id));
    }

    public function getUserAddrList() {
        $user_id = $this->_get('user_id');
        if (empty($user_id)) {
            return $this->failJSON('缺少参数');
        }
        return json($this->service->getUserAddrList($user_id));
    }

    public function getAddrByID() {
        $id = $this->_get('id');
        if (empty($id)) {
            return $this->failJSON('缺少参数');
        }
        return json($this->service->getAddrByID($id));
    }

    public function delAddr() {
        if (!IS_POST) {
            return $this->failJSON('使用POST提交');
        }
        $id = $this->_post('id');
        $user_id = $this->_post('user_id');
        if (!$user_id || !$id) {
            return $this->failJSON('缺少参数');
        }
        return json($this->service->delAddr($user_id, $id));
    }

    /**
     * 用户身份验证
     */
    public function userAuth() {
        if (!IS_POST) {
            return $this->failJSON('illegal request');
        }
        $user_id = $this->_post('user_id');
        return json($this->service->userAuth($user_id));
    }

    /*
     * 设定用户资料
     */
    public function setUserInfo() {
        if (!IS_POST) {
            return $this->failJSON('illegal request');
        }
        $user_id = $this->_post('user_id');
        if (!$user_id) {
            return $this->failJSON("illegal request");
        }
        $data['id'] = $this->_post('user_id');
        $data['email'] = $this->_post('email');
        $data['home_address'] = $this->_post('home_address');
        $data['city'] = $this->_post('city');
        $data['country'] = $this->_post('country');
        try {
            $m = new \addons\member\model\MemberAccountModel();
            $m->save($data);
            return $this->successJSON();
        } catch (\Exception $ex) {
            return $this->failJSON($ex->getMessage());
        }
    }

    /**
     * 验证手机是否已经注册
     */
    public function hasReg($phone){
        if (empty($phone)){
            return $this->failJSON('手机号不能为空');
        }
        $m = new \addons\member\model\MemberAccountModel();
        $count = $m->hasRegsterPhone($phone);
        return $this->successJSON($count);
    }

    /**
     * 用户修改密码
     */
    public function changePass() {
        if (!IS_POST) {
            return $this->failJSON('illegal request');
        }
        $username = $this->_post('username');
        $phone = $this->_post('phone');
        $code = $this->_post('code');
        $password = $this->_post('password');
        $type = $this->_post('type', 2);
        if (!preg_match("/^(?![0-9]+$)(?![a-zA-Z]+$)[0-9A-Za-z]{6,20}$/", $password)) {
            return $this->failJSON('请输入6~20位字母数字密码');
        }
        if (isset($phone) && isset($code) && isset($password) && isset($type)) {
            $m = new \addons\member\model\MemberAccountModel();
            try {
                $account = $m->getUserByUserName($username);
                if (!$account || $account && $account['phone'] != $phone) {
                    return $this->failJSON('用户名与手机号有误，无法重置密码');
                }

                $verifyM = new \addons\member\model\VericodeModel();
                $_verify = $verifyM->VerifyCode($code, $phone, $type);
                if (!empty($_verify)) {
                    $account['password'] = md5(md5($password) . $account['salt']);
//                    $id = $m->updatePassByUserName($username, $password);
                    $id = $m->save($account);
                    if ($id <= 0) {
                        return $this->failJSON('密码重置失败,请更换密码后重试 。reset the password is fail, please try again');
                    }
                    return $this->successJSON("修改成功");
                } else {
                    return $this->failJSON('验证码失效,请重新发送');
                }
            } catch (\Exception $ex) {
                return $this->failJSON($ex->getMessage());
            }
        } else {
            return $this->failJSON('missing arguments');
        }
    }

    public function setLoginPass() {
        if (!IS_POST) {
            return $this->failJSON('illegal request');
        }
        $user_id = $this->_post('user_id');
        $password = $this->_post('password');
        $now_password = $this->_post('pass2');
        $code = $this->_post('code');
        if (!$user_id || !$code || !$now_password) {
            return $this->failJSON("illegal request");
        }

        try {
            $m = new \addons\member\model\MemberAccountModel();
            $user = $m->getDetail($user_id, "phone,password,salt");
            $verifyM = new \addons\member\model\VericodeModel();
            $_verify = $verifyM->VerifyCode($code, $user['phone'], 5);
            if (!empty($_verify)) {
//                $password = md5($password);
//                if($password !== $user['password']){
//                    return $this->failJSON("原密码输入有误");
//                }

                if (!preg_match("/^(?![0-9]+$)(?![a-zA-Z]+$)[0-9A-Za-z]{6,20}$/", $now_password)) {
                    return $this->failJSON('请输入5~20位字母数字密码');
                }
                $now_password = md5(md5($now_password) . $user['salt']);


                $data['id'] = $user_id;
                $data['password'] = $now_password;
                $ret = $m->save($data); //用户id
                if (!$ret) {
                    return $this->failJSON('修改失败');
                }
                //添加资产记录
                $m->commit();
                return $this->successJSON();
            } else {
                $m->rollback();
                return $this->failJSON('验证码失效,请重新输入');
            }
        } catch (\Exception $ex) {
            return $this->failJSON($ex->getMessage());
        }
    }

    public function setPayPass() {
        if (!IS_POST) {
            return $this->failJSON('illegal request');
        }
        $user_id = $this->_post('user_id');
        $password = $this->_post('pass2');
        $now_password = $this->_post('pass2', 0);

        $code = $this->_post('code');
        if (!$user_id || !$code || !$now_password) {
            return $this->failJSON("illegal request");
        }
        if (!preg_match("/^[0-9]{6}$/", $now_password)) {
            return $this->failJSON('请输入6位数字交易密码');
        }

        try {
            $m = new \addons\member\model\MemberAccountModel();
            $user = $m->getDetail($user_id, "phone,pay_password");
            $verifyM = new \addons\member\model\VericodeModel();
            $_verify = $verifyM->VerifyCode($code, $user['phone'], 7);
            if (!empty($_verify)) {
                $now_password = md5($now_password);
                $data['id'] = $user_id;
                $data['pay_password'] = $now_password;
                $ret = $m->save($data); //用户id
                if (!$ret) {
                    return $this->failJSON('修改失败');
                }
                //添加资产记录
                $m->commit();
                return $this->successJSON();
            } else {
                $m->rollback();
                return $this->failJSON('验证码失效,请重新输入或发送');
            }
        } catch (\Exception $ex) {
            return $this->failJSON($ex->getMessage());
        }
    }


    /*
     * 获取用户推荐链接
     */
    public function getInviteQRCode(){
        $user_id = $this->_get('user_id');
        if (empty($user_id)) {
            return $this->failJSON('missing arguments');
        }
        try {
//            $m = new \addons\member\model\MemberAccountModel();
//            $info = $m->getDetail($user_id, 'username');
            $path = "http://www.wnct.io/login/register.html?code=" . $user_id;
            $ret['count'] = 0;
            $ret['path'] = $path;
            return $this->successJSON($ret);

        } catch (\Exception $ex) {
            return $this->failJSON($ex->getMessage());
        }
    }
}
