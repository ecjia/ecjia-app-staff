<?php
//
//    ______         ______           __         __         ______
//   /\  ___\       /\  ___\         /\_\       /\_\       /\  __ \
//   \/\  __\       \/\ \____        \/\_\      \/\_\      \/\ \_\ \
//    \/\_____\      \/\_____\     /\_\/\_\      \/\_\      \/\_\ \_\
//     \/_____/       \/_____/     \/__\/_/       \/_/       \/_/ /_/
//
//   上海商创网络科技有限公司
//
//  ---------------------------------------------------------------------------------
//
//   一、协议的许可和权利
//
//    1. 您可以在完全遵守本协议的基础上，将本软件应用于商业用途；
//    2. 您可以在协议规定的约束和限制范围内修改本产品源代码或界面风格以适应您的要求；
//    3. 您拥有使用本产品中的全部内容资料、商品信息及其他信息的所有权，并独立承担与其内容相关的
//       法律义务；
//    4. 获得商业授权之后，您可以将本软件应用于商业用途，自授权时刻起，在技术支持期限内拥有通过
//       指定的方式获得指定范围内的技术支持服务；
//
//   二、协议的约束和限制
//
//    1. 未获商业授权之前，禁止将本软件用于商业用途（包括但不限于企业法人经营的产品、经营性产品
//       以及以盈利为目的或实现盈利产品）；
//    2. 未获商业授权之前，禁止在本产品的整体或在任何部分基础上发展任何派生版本、修改版本或第三
//       方版本用于重新开发；
//    3. 如果您未能遵守本协议的条款，您的授权将被终止，所被许可的权利将被收回并承担相应法律责任；
//
//   三、有限担保和免责声明
//
//    1. 本软件及所附带的文件是作为不提供任何明确的或隐含的赔偿或担保的形式提供的；
//    2. 用户出于自愿而使用本软件，您必须了解使用本软件的风险，在尚未获得商业授权之前，我们不承
//       诺提供任何形式的技术支持、使用担保，也不承担任何因使用本软件而产生问题的相关责任；
//    3. 上海商创网络科技有限公司不对使用本产品构建的商城中的内容信息承担责任，但在不侵犯用户隐
//       私信息的前提下，保留以任何方式获取用户信息及商品信息的权利；
//
//   有关本产品最终用户授权协议、商业授权与技术服务的详细内容，均由上海商创网络科技有限公司独家
//   提供。上海商创网络科技有限公司拥有在不事先通知的情况下，修改授权协议的权力，修改后的协议对
//   改变之日起的新授权用户生效。电子文本形式的授权协议如同双方书面签署的协议一样，具有完全的和
//   等同的法律效力。您一旦开始修改、安装或使用本产品，即被视为完全理解并接受本协议的各项条款，
//   在享有上述条款授予的权力的同时，受到相关的约束和限制。协议许可范围以外的行为，将直接违反本
//   授权协议并构成侵权，我们有权随时终止授权，责令停止损害，并保留追究相关责任的权力。
//
//  ---------------------------------------------------------------------------------
//
defined('IN_ECJIA') or exit('No permission resources.');

/**
 * 管理员登录
 * @author will
 */
class v2_admin_user_signin_module extends api_admin implements api_interface {
    public function handleRequest(\Royalcms\Component\HttpKernel\Request $request) {
		$this->authadminSession();
		
		$username	= $this->requestData('username');
		$password	= $this->requestData('password');
		$device		= $this->device;
		
		$api_version = $this->request->header('api-version');
		$login_type = $this->requestData('type', 'password');
		$login_type_array = array('smslogin', 'password');
		//$device = array('code'=> '8001', 'udid' => '4adbe6e37384dc8f085c908f5ae2c093f5bb694f', 'client' => 'android', 'sn' => '00130136');
		
		if (empty($username) || empty($password)) {
			$result = new ecjia_error('login_error', __('您输入的帐号信息不正确', 'staff'));
			return $result;
		}
		
		if (version_compare($api_version, '1.14', '>=')) {
			if (empty($login_type) || !in_array($login_type, $login_type_array) || empty($username) || empty($password)) {
				return new ecjia_error('invalid_parameter', sprintf(__('请求接口%s参数无效', 'staff'), __CLASS__));
			}
			if ($login_type =='smslogin') {
				$user_count = RC_DB::table('staff_user')->where('mobile', $username)->count();
				if ($user_count > 1) {
					return new ecjia_error('user_repeat', __('用户重复，请与管理员联系！', 'staff'));
				}
				//短信验证码验证，$username手机号，$password短信验证码
				//判断校验码是否过期
				if (!empty($username) && (!isset($_SESSION['sms_code_lifetime']) || $_SESSION['sms_code_lifetime'] + 180 < RC_Time::gmtime())) {
					//过期
					return new ecjia_error('code_timeout', __('验证码已过期，请重新获取！', 'staff'));
				}
				//判断校验码是否正确
				if (!empty($username) && (!isset($_SESSION['sms_code_lifetime']) || $password != $_SESSION['sms_code'] )) {
					return new ecjia_error('code_error', __('验证码错误，请重新填写！', 'staff'));
				}
			}
		}		
		
		//根据用户名判断是商家还是平台管理员
		//如果商家员工表存在，以商家为准
		$row_staff = RC_DB::table('staff_user')->where('mobile', $username)->first();
		//员工所在店铺有没被锁定；锁定不可登录
		if ($row_staff['store_id']) {
			$store_status 	= Ecjia\App\Cart\StoreStatus::GetStoreStatus($row_staff['store_id']);
			if ($store_status == Ecjia\App\Cart\StoreStatus::LOCKED) {
				return new ecjia_error('store_locked', __('对不起，该店铺已锁定，请联系平台管理员！', 'staff'));
			}
		}
		
		if ($row_staff) {
		    //商家
		    return $this->signin_merchant($username, $password, $device, $api_version, $login_type, $request);
		} else {
		    //平台
		    $result = new ecjia_error('login_error', __('此账号不是商家账号', 'staff'));
		    return $result;
		}
	}

    private function signin_merchant($username, $password, $device, $api_version, $login_type = '', $request = null) {
        /* 收银台请求判断处理*/
        $codes = array('8001', '8011');
        if (!empty($device) && is_array($device) && in_array($device['code'], $codes)) {
            $staff_user_info = RC_DB::table('staff_user')->where('mobile', $username)->first();
            if (empty($staff_user_info)) {
                $result = new ecjia_error('login_error', __('您输入的帐号信息不正确', 'staff'));
                return $result;
            }
            $device_sn = trim($device['sn']); 
            //当前登录的收银设备是否是当前店铺的
            $cashier_device_info = RC_DB::table('cashier_device')->where('store_id', $staff_user_info['store_id'])->where('device_sn', $device_sn)->first();
            if (empty($cashier_device_info)) {
            	return new ecjia_error('cashier_device_error', __('此设备不属于当前店铺设备，请使用当前店铺设备登录！', 'staff'));
            }
            $username   = $staff_user_info['mobile'];
            $salt       = $staff_user_info['salt'];
        } else {
            $salt = RC_DB::table('staff_user')->where('mobile', $username)->pluck('salt');
            $salt = trim($salt);
        }
       
        /* 检查密码是否正确 */
        $db_staff_user = RC_DB::table('staff_user')->select('user_id', 'mobile', 'name', 'store_id', 'nick_name', 'email', 'last_login', 'last_ip', 'action_list', 'avatar', 'group_id', 'online_status');
        if (version_compare($api_version, '1.14', '>=')) {
            if ($login_type == 'smslogin') {
                $db_staff_user->where('mobile', $username);
            } else {
                if (!empty($salt)) {
                    //md5(md5($password).$salt)
                    $md5_password = md5($password);
                    $password_final = md5($md5_password.$salt);
                    $db_staff_user->where('mobile', $username)->where('password', $password_final);
                } else {
                    $db_staff_user->where('mobile', $username)->where('password', md5($password));
                }
            }
        } else {
            if (!empty($salt)) {
                //md5(md5($password).$salt)
                $md5_password = md5($password);
                $password_final = md5($md5_password.$salt);
                
                $db_staff_user->where('mobile', $username)->where('password',$password_final);
            } else {
                $db_staff_user->where('mobile', $username)->where('password', md5($password));
            }
        }
       
        $row = $db_staff_user->first();
       
        if ($row) {
            // 登录成功
            /* 设置session信息 */
            /*  
             [store_id] => 15
             [store_name] => 天天果园专营店
             [staff_id] => 1
             [staff_mobile] => 15921158110
             [staff_name] => hyy
             [staff_email] => hyy
             [last_login] => 1476816441
             adviser_id
             shop_guide
             [admin_id] => 0
             [admin_name] => 0
             [action_list] => all
             [email] => 0
             [device_id]
             [ip] => 0.0.0.0
              */

            $this->admin_session($row['user_id'], $row['name'], $row['action_list'], $row['last_login'], $row['mobile'], $row['email']);
        
            $_SESSION['admin_id']       = 0;
            $_SESSION['admin_name']     = null;

            $_SESSION['store_id']       = $row['store_id'];
            $_SESSION['store_name']     = RC_DB::table('store_franchisee')->where('store_id', $row['store_id'])->pluck('merchants_name');

            
            /* 获取device_id*/
            $device_id = RC_DB::table('mobile_device')
                            ->where('device_udid', $device['udid'])
                            ->where('device_client', $device['client'])
                            ->where('device_code', $device['code'])
                            ->pluck('id');
            
            $_SESSION['device_id'] = $device_id;
        
            if ($row['action_list'] == 'all' && empty($row['last_login'])) {
                $_SESSION['shop_guide'] = true;
            }
        
            $data = array(
                'last_login'    => RC_Time::gmtime(),
                'last_ip'       => RC_Ip::client_ip(),
            );
            RC_DB::table('staff_user')->where('user_id', $_SESSION['staff_id'])->update($data);
            
            $out = array(
                'session' => array(
                    'sid' => RC_Session::session_id(),
                    'uid' => $_SESSION['staff_id']
                ),
            );
            $role_name = $group = '';
            
            switch ($row['group_id']) {
                case -1 : 
                    $role_name  = "配送员";
                    $group      = 'express';
                    $role_type = 'express_user';
                    break;
                case -2 :
                    $role_name  = "收银员";
                    $group      = 'cashier';
                    $role_type  = 'cashier';
                    break;
                default:
                    if ($row['group_id'] > 0) {
                        $role_name = RC_DB::table('staff_group')->where('group_id', $row['group_id'])->pluck('group_name');
                    }
                    $role_type = '';
                    break;
            }
            
            /* 登入后默认设置离开状态*/
            if ($row['online_status'] != 4 && $group == 'express') {
                RC_DB::table('staff_user')->where('user_id', $_SESSION['staff_id'])->update(array('online_status' => 4));
                /* 获取当前时间戳*/
                $time = RC_Time::gmtime();
                $fomated_time = RC_Time::local_date('Y-m-d', $time);
                /* 查询签到记录*/
                $checkin_log = RC_DB::table('express_checkin')->where('user_id', $_SESSION['staff_id'])->orderBy('log_id', 'desc')->first();
                if ($fomated_time == $checkin_log['checkin_date'] && empty($checkin_log['end_time'])) {
                    $duration = $time - $checkin_log['start_time'];
                    RC_DB::table('express_checkin')->where('log_id', $checkin_log['log_id'])->update(array('end_time' => $time, 'duration' => $duration));
                }
            }
            /*返回connect_user表中open_id和access_token*/
            $out['userinfo'] = array(
                'seller_id'     => $row['store_id'],
            	'store_id'		=> $row['store_id'],
                'id'            => $row['user_id'],
                'username'      => $row['name'],
                'mobile'        => $row['mobile'],
                'email'         => $row['email'],
                'last_login'    => RC_Time::local_date(ecjia::config('time_format'), $row['last_login']),
                'last_ip'       => RC_Ip::area($row['last_ip']),
                'role_name'     => $role_name,
                'role_type'     => $role_type,
                'group'         => $group,
                'avator_img'    => !empty($row['avatar']) ? RC_Upload::upload_url($row['avatar']) : '',
            );
            
            //ecjia账号同步登录用户信息更新
            $open_id 		= md5(RC_Time::gmtime().$_SESSION['staff_id']);
            $access_token 	= RC_Session::session_id();
            $refresh_token 	= md5($_SESSION['staff_id'].'merchant_refresh_token');
            $connect_options = [
	            'connect_code'  => 'app',
	            'user_id'       => $_SESSION['staff_id'],
	            'is_admin'      => '1',
	            'user_type'     => 'merchant',
	            'open_id'       => $open_id,
	            'access_token'  => $access_token,
	            'refresh_token' => md5($_SESSION['staff_id'] . 'merchant_refresh_token'),
            ];
            $ecjiaAppUser = RC_Api::api('connect', 'ecjia_syncappuser_add', $connect_options);
            if (is_ecjia_error($ecjiaAppUser)) {
            	return $ecjiaAppUser;
            }
            
            $out['userinfo']['open_id'] 		= $open_id;
            $out['userinfo']['access_token'] 	= $access_token;
            $out['userinfo']['refresh_token'] 	= $refresh_token;
            $out['userinfo']['user_type']		= 'merchant';
                    
            //修正关联设备号
            RC_Api::api('mobile', 'bind_device_user', array(
                'device_udid'   => $request->header('device-udid'),
                'device_client' => $request->header('device-client'),
                'device_code'   => $request->header('device-code'),
                'user_type'     => 'merchant',
                'user_id'       => session('session_user_id'),
            ));
         
            return $out;
        } else {
            return new ecjia_error('login_error', __('您输入的帐号信息不正确', 'staff'));
        }
    }
}

// end