<?php

/**
 * object Bootstrap
 *
 * @package    Bootstrap.php 
 * @author     Ionut Andrei Baches <ionut.baches@dcsplus.net>
 * @copyright  dcsplus.net
 * @since      2016-08-18
 */

namespace ApplicationTest;

use Zend\Loader\AutoloaderFactory;
use Zend\Mvc\Service\ServiceManagerConfig;
use Zend\ServiceManager\ServiceManager;
use RuntimeException;

error_reporting(E_ALL | E_STRICT);
chdir( __DIR__.'/../../');

define('APP_PATH', getcwd());

/**
 * Test bootstrap, for setting up autoloading
 */
class Bootstrap
{
    protected static $serviceManager;

    public static function init()
    {   
        $xpath = APP_PATH.'/config/';
        /*foreach (glob("$xpath/*.php") as $filename){
            include $filename;
        }*/

        if(file_exists($xpath.'constants.config.php')){
            include_once $xpath.'constants.config.php';
        }

        $zf2ModulePaths = array(dirname(dirname(__DIR__)));

        if (($path = static::findParentPath('vendor'))) {
            $zf2ModulePaths[] = $path;
        }
        if (($path = static::findParentPath('module')) && $path !== $zf2ModulePaths[0]) {
            $zf2ModulePaths[] = $path;
        }

        static::initAutoloader();
        // use ModuleManager to load this module and it's dependencies
         $config = array(
            'module_listener_options' => array(
                'module_paths' => $zf2ModulePaths,
                'config_glob_paths' => array(
                    $xpath.'autoload/{,*.}{global,local}.php',
                )
            ),

            'modules' => include $xpath . '/modules.config.php',
            
        );
        if(file_exists($xpath.'application.config.php')){
            $config =  include $xpath.'application.config.php';
        }

        if(file_exists($xpath.'development.config.php')){
            $configB = include $xpath. 'development.config.php';
        }else{
            $configB = array();
        }

        $config = \Zend\Stdlib\ArrayUtils::merge($config, $configB);
        $serviceManager = new ServiceManager(new ServiceManagerConfig());
        $serviceManager->setService('ApplicationConfig', $config);
        $serviceManager->get('ModuleManager')->loadModules();
        static::$serviceManager = $serviceManager;


    }

    public static function chroot()
    {
        $rootPath = dirname(static::findParentPath('module'));
        chdir($rootPath);
    }

    public static function getServiceManager()
    {
        return static::$serviceManager;
    }

    protected static function initAutoloader()
    {
        $vendorPath = APP_PATH.'/vendor';
        if (file_exists($vendorPath.'/autoload.php')) {
            include $vendorPath.'/autoload.php';
        }

        if (! class_exists('Zend\Loader\AutoloaderFactory')) {
            throw new RuntimeException(
                'Unable to load ZF2. Run `php composer.phar install`'
            );
        }

        AutoloaderFactory::factory(array(
            'Zend\Loader\StandardAutoloader' => array(
                'autoregister_zf' => true,
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__ . '/' . __NAMESPACE__,
                ),
            ),
        ));
    }

    protected static function findParentPath($path)
    {
        $dir = APP_PATH;

        $previousDir = '.';
        while (!is_dir($dir . '/' . $path)) {
            $dir = dirname($dir);
            if ($previousDir === $dir) {
                return false;
            }
            $previousDir = $dir;
        }
       
        return $dir . '/' . $path;
    }
}

Bootstrap::init();
Bootstrap::chroot();