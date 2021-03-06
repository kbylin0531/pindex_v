<?php
/**
 * Created by PhpStorm.
 * User: lnzhv
 * Date: 7/13/16
 * Time: 10:09 PM
 */

namespace PLite\Core;
use PLite\Lite;
use PLite\Debugger;
use PLite\PLiteException;
use PLite\Util\SEK;


/**
 * Class Dispatcher
 * 将URI解析结果调度到指定的控制器下的方法下
 * @package PLite\Core
 */
class Dispatcher extends Lite {

    const CONF_NAME = 'dispatcher';
    const CONF_CONVENTION = [
        //空缺时默认补上,Done!
        'INDEX_MODULE'      => 'Home',
        'INDEX_CONTROLLER'  => 'Index',
        'INDEX_ACTION'      => 'index',

    ];

    private static $_module = null;
    private static $_controller = null;
    private static $_action = null;

    /**
     * 匹配空缺补上默认
     * @param string|array $modules
     * @param string $ctrler
     * @param string $action
     * @return void
     */
    public static function checkDefault($modules,$ctrler,$action){
        $config = self::getConfig();
        self::$_module      = $modules?$modules:$config['INDEX_MODULE'];
        self::$_controller  = $ctrler?$ctrler:$config['INDEX_CONTROLLER'];
        self::$_action      = $action?$action:$config['INDEX_ACTION'];
        is_array($modules) and self::$_module = SEK::toModulesString($modules,'/');
//        \PLite\dumpout(self::$_module,self::$_controller,self::$_action);
    }

    /**
     * 制定对应的方法
     * @param string $modules
     * @param string $ctrler
     * @param string $action
     * @return mixed
     * @throws PLiteException
     */
    public static function exec($modules=null,$ctrler=null,$action=null){
        null === $modules   and $modules = self::$_module;
        null === $ctrler    and $ctrler = self::$_controller;
        null === $action    and $action = self::$_action;

        Debugger::trace($modules,$ctrler,$action);

        $modulepath = PATH_BASE."/Application/{$modules}";//linux 不识别 \\

        strpos($modules,'/') and $modules = str_replace('/','\\',$modules);

        //模块检测
        is_dir($modulepath) or PLiteException::throwing('Module not found!',$modules);

        //在执行方法之前定义常量,为了能在控制器的构造函数中使用这三个常量
        define('REQUEST_MODULE',$modules);//请求的模块
        define('REQUEST_CONTROLLER',$ctrler);//请求的控制器
        define('REQUEST_ACTION',$action);//请求的操作

        //控制器名称及存实性检测
        $className = "Application\\{$modules}\\Controller\\{$ctrler}";
        class_exists($className) or PLiteException::throwing($modules,$className);
//        \PLite\dumpout($modules,$ctrler,$action);
        $classInstance =  new $className();
        //方法检测
        method_exists($classInstance,$action) or PLiteException::throwing($modules,$className,$action);
        $method = new \ReflectionMethod($classInstance, $action);

        $result = null;
        if ($method->isPublic() and !$method->isStatic()) {//仅允许非静态的公开方法
            //方法的参数检测
            if ($method->getNumberOfParameters()) {//有参数
                $args = self::fetchMethodArguments($method);
                //执行方法
                $result = $method->invokeArgs($classInstance, $args);
            } else {//无参数的方法调用
                $result = $method->invoke($classInstance);
            }
        } else {
            PLiteException::throwing($className, $action);
        }

        Debugger::status('execute_end');
        return $result;
    }



    /**
     * 获取传递给盖饭昂奋的参数
     * @param \ReflectionMethod $targetMethod
     * @return array
     * @throws PLiteException
     */
    private static function fetchMethodArguments(\ReflectionMethod $targetMethod){
        //获取输入参数
        $vars = $args = [];
        switch(strtoupper($_SERVER['REQUEST_METHOD'])){
            case 'POST':$vars    =  array_merge($_GET,$_POST);  break;
            case 'PUT':parse_str(file_get_contents('php://input'), $vars);  break;
            default:$vars  =  $_GET;
        }
        //获取方法的固定参数
        $methodParams = $targetMethod->getParameters();
        //遍历方法的参数
        foreach ($methodParams as $param) {
            $paramName = $param->getName();

            if(isset($vars[$paramName])){
                $args[] =   $vars[$paramName];
            }elseif($param->isDefaultValueAvailable()){
                $args[] =   $param->getDefaultValue();
            }else{
                return PLiteException::throwing("目标缺少参数'{$param}'!");
            }
        }
        return $args;
    }

    /**
     * 加载当前访问的模块的指定配置
     * 配置目录在模块目录下的'Common/Conf'
     * @param string $name 配置名称,多个名称以'/'分隔
     * @param string $type 配置类型,默认为php
     * @return array
     */
    public static function load($name,$type=Configger::TYPE_PHP){
        if(!defined('REQUEST_MODULE')) return PLiteException::throwing('\'load\'必须在\'exec\'方法之后调用!');//前提是正确制定过exec方法
        $path = PATH_BASE.'/Application/'.REQUEST_MODULE.'/Common/Config/';
//        \PLite\dumpout($path);
        $storage = Storage::getInstance();
        if($storage->has($path) === Storage::IS_DIR){
            $file = "{$path}/{$name}.".$type;
            return Configger::load($file);
        }
        return [];
    }

}