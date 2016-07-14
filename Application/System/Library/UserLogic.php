<?php
/**
 * Created by PhpStorm.
 * User: lnzhv
 * Date: 7/14/16
 * Time: 2:36 PM
 */

namespace Application\System\Library;
use Application\System\Model\UserModel;
use PLite\Library\Cookie;
use PLite\Library\Session;
use PLite\Lite;
use PLite\Util\Encrypt\Base64;

class UserLogic extends Lite{

    private static $key = '_userinfo_';

    /**
     * check the current user login status
     * @return bool
     */
    public function isLogin(){
        $session = Session::getInstance();
        $status = $session->get(self::$key);//return null if not set
        if(!$status){
            $cookie = Cookie::getInstance()->get(self::$key);
            if($cookie){
                $usrinfo = unserialize(Base64::decrypt($cookie, self::$key));
                $session->set(self::$key, $usrinfo);
                return true;
            }
        }
        return $status?true:false;
    }


    /**
     * @param string $username
     * @param null $password
     * @param bool $remember
     * @return bool|string 返回的string代表着错误的信息，返回true表示登陆成功
     */
    public function login($username,$password,$remember){
        $model = new UserModel();
        $usrinfo = $model->checkLogin($username,$password);
//        \Soya\dumpout($usrinfo);
        if(false === $usrinfo){
//            \Soya\dumpout($model->error());
            return $model->error();
        }

        //set session,browser must enable cookie
        if($remember){
            $sinfo = serialize($usrinfo);
            $cookie = Base64::encrypt($sinfo, self::$key);
            Cookie::getInstance()->set(self::$key, $cookie,7*24*3600);//一周的时间
        }
        Session::getInstance()->set(self::$key, $usrinfo);
        return true;
    }

    /**
     * 获取登录信息
     * @param string $name 信息名称
     * @return array|false|null|mixed 发生了错误时返回FALSE
     */
    public function getLoginInfo($name=null){
        static $info = null;
        if(null === $info){
            $info = Session::get(self::$key);
            if(null === $info){
                //用户未登录,按照情况执行抛出异常操作或者返回null
                return false;//'用户未登录，无法执行该操作！'
            }
        }

        if($name){
            return isset($info[$name])?$info[$name]:null;
        }
        return $info;
    }

    /**
     * 注销登陆
     * @return void
     */
    public function logout(){
        Session::delete(self::$key);
        Cookie::clear(self::$key);
    }



}