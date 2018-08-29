<?php
/**
 * Created by PhpStorm.
 * User: SUN
 * Date: 2018/8/23
 * Time: 15:15
 */

namespace web\mobile\controller;


use think\Request;
use think\Validate;

class User extends Base
{
    private $_address = array();
    private $ethPass = '';

    public function login()
    {
        if(IS_POST)
        {
            try
            {
                $param = Request::instance()->post();
                $validate = new Validate([
                    'phone|手机号' => ['require','regex' => '/(13\d|14[57]|15[^4,\D]|17[13678]|18\d)\d{8}|170[0589]\d{7}/'],
                    'password|密码'  => 'require'
                ]);
                if(!$validate->check($param))
                {
                    return $this->failData($validate->getError());
                }

                $m = new \addons\member\model\MemberAccountModel();
                $res = $m->getLoginData($param['password'],$param['phone'],'','id,username,address');
                if(!$res)
                {
                    return $this->failData('账号或密码有误');
                }

                $memberData['user_id']  = $res['id'];
                $memberData['username'] = $res['username'];
                $memberData['address']  = $res['address'];
                session('memberData',$memberData);

                return $this->successData();
            }catch (\Exception $e)
            {
                return $this->failData($e->getMessage());
            }
        }else
        {
            return $this->fetch();
        }

    }

    public function logout()
    {
        $memberData = session('memberData');
        if(empty($memberData))
            return $this->successData();
        session('memberData', null);
        return $this->successData();
    }

    public function register()
    {
        if(IS_POST)
        {
            return $this->successData();
            $param = Request::instance()->post();

            $validate = new Validate([
                'username|用户名'  => 'require',
                'password|登录密码'    => 'require',
                'password_confirm|登录密码' => 'require|confirm',
                'pay_password|交易密码'     => 'require',
                'pay_password_confirm|交易密码'  => 'require|confirm',
                'phone|手机号' => 'require',
                'verify_code|验证码'   => 'require',
                'invite_phone|推荐人'  => ['require','regex' => '/(13\d|14[57]|15[^4,\D]|17[13678]|18\d)\d{8}|170[0589]\d{7}/']
            ],[
                'password_confirm'  => '两次输入的登录密码不一致',
                'pay_password_confirm'  => '两次输入的交易密不一致',
                'invite_phone'  => '推荐人手机号有误'
            ]);
            if(!$validate->check($param))
            {
                return $this->failData($validate->getError());
            }

            $data = array(
                'username'  => $param['username'],
                'phone'     => $param['phone'],
                'password'  => md5($param['password']),
                'pay_password'  => md5($param['pay_password'])
            );

            $m = new \addons\member\model\MemberAccountModel();
            $count = $m->hasRegsterPhone($param['phone']);
            if($count > 0)
            {
                return $this->failData('此手机号已被注册，请直接登录或尝试找回密码');
            }
            $m->startTrans();
            try{
                $verfyM = new \addons\member\model\VericodeModel();
                $_verify = $verfyM->VerifyCode($param['verify_code'],$param['phone']);
                if(!empty($_verify))
                {
                    $invite_user_id = $m->getUserByPhone($param['phone']);
                    $data['pid'] = $invite_user_id;

                    $data['register_time'] = NOW_DATETIME;
//                $res = $this->getEthAddr($param['phone']);
                    $res = true;
                    if($res)
                    {
//                    $data['address'] = $this->_address;
//                    $data['eth_pass'] = $this->ethPass;
                        $m->add($data);
                        $m->commit();

                        return $this->successData();
                    }
                }else
                {
                    $m->rollback();
                    return $this->failData('验证码失效，请重新获取');
                }
            }catch (\Exception $e)
            {
                return $this->failData($e->getMessage());
            }
        }else
        {
            $this->assign('time',60*3);
            return $this->fetch();
        }
    }

    private function getEthAddr($name)
    {
        $eth_pass = 'token'.$name.rand(0000,9999);
        $this->ethPass = $eth_pass;
        $res = $this->jsonrpc('personal_newAccount',[$eth_pass]);

        return $res;
    }

    private function jsonrpc($method, $params)
    {
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

    /**
     * 验证手机是否已经注册
     */
    public function hasReg($phone){
        $m = new \addons\member\model\MemberAccountModel();
        $count = $m->hasRegsterPhone($phone);
        return $this->successJSON($count);
    }
}