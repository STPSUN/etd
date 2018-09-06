<?php

namespace addons\member\model;

/**
 * 会员账户信息
 * 
 * @author shilinqing
 */
class MemberAccountModel extends \web\common\model\BaseModel {

    protected function _initialize() {
        $this->tableName = 'member_account';
    }

    /**
     * get user login info . (filed phone and wallet_name) one of them has to be filled out
     * @param type $password
     * @param type $phone   
     * @param type $wallet_name
     * @param type $fields
     * @return boolean
     */
    public function getLoginData($password, $phone = '', $wallet_name = '', $fields = 'id,username,address,is_auth') {
        $sql = 'select ' . $fields . ' from ' . $this->getTableName() . ' where logic_delete=0';
        if (!empty($phone)) {
            $sql .= ' and phone=\'' . $phone . '\'';
        } else if (!empty($wallet_name)) {
            $sql .= ' and wallet_name=\'' . $wallet_name . '\'';
        } else {
            return false;
        }
        $sql .= ' and password=\'' . md5($password) . '\'';
        $result = $this->query($sql);
        if (!empty($result) && count($result) > 0)
            return $result[0];
        else
            return null;
    }
    
    /**
     * 短信验证登录时 只有电话号码用来查询用户信息
     * get user login info . filed phone  has to be filled out
     * @param type $phone   
     * @param type $fields
     * @return boolean
     */
    public function getLoginDataBySms($phone = '', $fields = 'id,username,address,is_auth') {
        $sql = 'select ' . $fields . ' from ' . $this->getTableName() . ' where logic_delete=0';
        if (!empty($phone)) {
            $sql .= ' and phone=\'' . $phone . '\'';
        } else {
            return false;
        }
        $result = $this->query($sql);
        if (!empty($result) && count($result) > 0)
            return $result[0];
        else
            return null;
    }
    
    public function getNewLoginData($field_name = '' ,$field_value = '',$password,  $fields = 'id,username,address,is_auth') {
        $where = [
            $field_name => $field_value,
            'logic_delete' => 0,
        ];
        $info = $this->where($where)->field($fields)->find();
        if(!$info){
            $this->error = "账号或密码错误";
            return false;
        }
        $mdPass = md5($password);
        if($mdPass !== $info['password']){
            $this->error = "账号或密码错误";
            return false;
        }
        return $info;
    }

    /**
     * verify the user's phone is registered or not
     * @param type $phone
     * @return type
     */
    public function hasRegsterPhone($phone) {
        $where['phone'] = $phone;
        return $this->where($where)->count();
    }

    /**
     * verify the user's username is registered or not
     * @param type $phone
     * @return type
     */
    public function hasRegsterUsername($username) {
        $where['username'] = $username;
        return $this->where($where)->count();
    }
    /**
     * verify the user's wallet name is registered or not
     * @param type $name
     * @return type
     */
    public function hasRegsterWallet($name) {
        $where['wallet_name'] = $name;
        return $this->where($where)->count();
    }

    /**
     * update the user password by phone number
     * @param type $phone
     * @param type $password
     * @param type $type    2=login password ,3 = payment password
     * @return int
     */
    public function updatePassByPhone($phone, $password, $type = 2) {
        if ($type == 2) {
            $data['password'] = $password;
        } else if ($type == 3) {
            $data['pay_password'] = $password;
        } else {
            return 0;
        }
        $where['phone'] = $phone;
        return $this->where($where)->update($data);
    }

    /**
     * get user by invite code 
     * @param type $invite_code
     * @return int
     */
    public function getUserByInviteCode($invite_code) {
        $where['invite_code'] = $invite_code;
        $res = $this->where($where)->field('id')->find();
        if (!empty($res)) {
            return $res['id'];
        } else {
            return 0;
        }
    }

    /**
     * get user parent id
     * @param type $user_id
     * @return type
     */
    public function getPID($id) {
        $where['id'] = $id;
        $ret = $this->where($where)->field('pid')->find();
        return $ret['pid'];
    }

    /**
     * get user eth address
     * @param type $user_id
     */
    public function getUserAddress($id) {
        $where['id'] = $id;
        $data = $this->where($where)->field('address')->find();
        return $data['address'];
    }

    /**
     * get user by the eth address
     * @param type $address
     * @return int
     */
    public function getUserByAddress($address) {
        $where['address'] = $address;
        $data = $this->where($where)->find();
        if (!empty($data)) {
            return $data['id'];
        } else {
            return 0;
        }
    }

    /**
     * get user by the username
     * @param type $address
     * @return int
     */
    public function getUserByUsername($username) {
        $where['username'] = $username;
        $data = $this->where($where)->find();
        if (!empty($data)) {
            return $data['id'];
        } else {
            return 0;
        }
    }

    public function getUserByPhone($phone)
    {
        $where['phone'] = $phone;
        $data = $this->where($where)->find();
        if(!empty($data))
        {
            return $data['id'];
        }else
        {
            return 0;
        }
    }

    /**
     * get user authentication data
     * @param type $id
     * @param type $fields
     * @return type
     */
    public function getAuthData($id, $fields = 'real_name,card_no,id_face,id_back') {
        $where['id'] = $id;
        return $this->where($where)->field($fields)->find();
    }

    /**
     * return user authentication status
     * @param type $id
     */
    public function getAuthByUserID($id) {
        $where['id'] = $id;
        $auth = $this->where($where)->field('is_auth')->find();
        return $auth['is_auth'];
    }

    /**
     * change user account frozen status
     * @param type $id
     * @param type $status default 1
     * @return type
     */
    public function changeFrozenStatus($id, $status = 1) {
        $where['id'] = $id;
        $data['is_frozen'] = $status;
        return $this->where($where)->update($data);
    }
    
}
