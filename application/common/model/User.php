<?php
// +----------------------------------------------------------------------
// | SentCMS [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013 http://www.tensent.cn All rights reserved.
// +----------------------------------------------------------------------
// | Author: molong <molong@tensent.cn> <http://www.tensent.cn>
// +----------------------------------------------------------------------

namespace app\common\model;

/**
* 用户模型
*/
class User extends \think\model\Merge{

	protected $name = "Member";
	protected static $relationModel = array('MemberExtend');
	protected $createTime = 'reg_time';
	protected $updateTime = 'last_login_time';
	protected $fk = 'user_id';
	protected $mapFields = array(
		'uid'        =>  'Member.uid',
		'user_id'        =>  'MemberExtend.uid',
	);
	protected $type = array(
		'id'  => 'integer',
	);
	protected $insert = array('salt', 'password', 'status', 'reg_time');
	protected $update = array();

	public $editfield = array(
		array('name'=>'uid','type'=>'hidden'),
		array('name'=>'username','title'=>'用户名','type'=>'readonly','help'=>''),
		array('name'=>'nickname','title'=>'昵称','type'=>'text','help'=>''),
		array('name'=>'password','title'=>'密码','type'=>'password','help'=>'为空时则不修改'),
		array('name'=>'sex','title'=>'性别','type'=>'select','option'=>array('0'=>'保密','1'=>'男','2'=>'女'),'help'=>''),
		array('name'=>'email','title'=>'邮箱','type'=>'text','help'=>'用户邮箱，用于找回密码等安全操作'),
		array('name'=>'qq','title'=>'QQ','type'=>'text','help'=>''),
		array('name'=>'score','title'=>'用户积分','type'=>'text','help'=>''),
		array('name'=>'signature','title'=>'用户签名','type'=>'textarea','help'=>''),
		array('name'=>'status','title'=>'状态','type'=>'select','option'=>array('0'=>'禁用','1'=>'启用'),'help'=>''),
	);

	public $addfield = array(
		array('name'=>'username','title'=>'用户名','type'=>'text','help'=>'用户名会作为默认的昵称'),
		array('name'=>'password','title'=>'密码','type'=>'password','help'=>'用户密码不能少于6位'),
		array('name'=>'repassword','title'=>'确认密码','type'=>'password','help'=>'确认密码'),
		array('name'=>'email','title'=>'邮箱','type'=>'text','help'=>'用户邮箱，用于找回密码等安全操作'),
	);
    
	public $useredit = array(
		array('name'=>'uid','type'=>'hidden'),
		array('name'=>'nickname','title'=>'昵称','type'=>'text','help'=>''),
		array('name'=>'sex','title'=>'性别','type'=>'select','option'=>array('0'=>'保密','1'=>'男','2'=>'女'),'help'=>''),
		array('name'=>'email','title'=>'邮箱','type'=>'text','help'=>'用户邮箱，用于找回密码等安全操作'),
		array('name'=>'qq','title'=>'QQ','type'=>'text','help'=>''),
		array('name'=>'score','title'=>'用户积分','type'=>'text','help'=>''),
		array('name'=>'signature','title'=>'用户签名','type'=>'textarea','help'=>''),
	);

	protected function setStatusAttr($value){
		return 1;
	}

	protected function setPasswordAttr($value){
		return md5($value.$this->data['salt']);
	}

	/**
	* 用户登录模型
	*/
	public function login($username = '', $password = '', $type = 1){
		$map = array();
		switch ($type) {
			case 1:
				$map['username'] = $username;
				break;
			case 2:
				$map['email'] = $username;
				break;
			case 3:
				$map['mobile'] = $username;
				break;
			case 4:
				$map['uid'] = $username;
				break;
			case 5:
				$map['uid'] = $username;
				break;
			default:
				return 0; //参数错误
		}

		$user = $this->db()->where($map)->find()->toArray();
		if(is_array($user) && $user['status']){
			/* 验证用户密码 */
			if(md5($password.$user['salt']) === $user['password']){
				$this->autoLogin($user); //更新用户登录信息
				return $user['uid']; //登录成功，返回用户ID
			} else {
				return -2; //密码错误
			}
		} else {
			return -1; //用户不存在或被禁用
		}
	}

	/**
	 * 用户注册
	 * @param  integer $user 用户信息数组
	 */
	function register($username, $password, $repassword, $isautologin = true){
		if ($password !== $repassword) {
			$this->error = "密码和确认密码不相同";
			return false;
		}

		$data['username'] = $username;
		$data['salt'] = rand_string(6);
		$data['password'] = $password;
		$result = $this->validate(true)->save($data);
		if ($result) {
			$this->data['uid'] = $result;
			if ($isautologin) {
				$this->autoLogin($this->data);
			}
			return $result;
		}else{
			if (!$this->getError()) {
				$this->error = "注册失败！";
			}
			return false;
		}
	}

	/**
	 * 自动登录用户
	 * @param  integer $user 用户信息数组
	 */
	private function autoLogin($user){
		/* 更新登录信息 */
		$data = array(
			'uid'             => $user['uid'],
			'login'           => array('exp', '`login`+1'),
			'last_login_time' => time(),
			'last_login_ip'   => get_client_ip(1),
		);
		$this->db()->where(array('uid'=>$user['uid']))->update($data);
		$user = $this->db()->where(array('uid'=>$user['uid']))->find();

		/* 记录登录SESSION和COOKIES */
		$auth = array(
			'uid'             => $user['uid'],
			'username'        => $user['username'],
			'last_login_time' => $user['last_login_time'],
		);

		session('user_auth', $auth);
		session('user_auth_sign', data_auth_sign($auth));
	}

	public function logout(){
		session('user_auth', null);
		session('user_auth_sign', null);
	}

	public function getInfo($uid){
		$data = $this->db()->where(array('uid'=>$uid))->find();
		if ($data) {
			return $data->toArray();
		}else{
			return false;
		}
	}

	public function change(){
		$data = input('post.');
		if ($data['uid']) {
			return $this->save($data, array('uid'=>$data['uid']));
		}else{
			$this->error = "非法操作！";
			return false;
		}
	}

	public function editpw(){
		$data = input('post.');
		$username = session('user_auth.username');
		$uid = session('user_auth.uid');
		$result = $this->checkPassword($username,$data['oldpassword']);
		if (!$result) {
			$this->error = '原始密码错误！';
			return false;
		}
		if (!$data['password']) {
			$this->error = '密码不能为空！';
			return false;
		}
		if ($data['password'] !== $data['repassword']) {
			$this->error = '密码和确认密码不相同！';
			return false;
		}
		if (!$uid) {
			return false;
		}
		$data['salt'] = rand_string(6);
		$data['password'] = md5($data['password'].$data['salt']);
		$data['uid'] = $uid;
		return $this->db()->where(array('uid'=>$uid))->update($data);
	}

	protected function checkPassword($username,$password){
		if (!$username || !$password) {
			return false;
		}

		$user = $this->db()->where(array('username'=>$username))->find()->toArray();
		if (md5($password.$user['salt']) === $user['password']) {
			return true;
		}else{
			return false;
		}
	}
}