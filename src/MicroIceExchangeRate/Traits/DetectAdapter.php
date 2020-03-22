<?php
namespace MicroIceExchangeRate\Traits;

/**
 * Provides a class method to get the DB adapter either from DBManager or from config files
 */
trait DetectAdapter
{
    function getAdapter($services)
    {
        // we first check for DbManager as we plan to make it a standalone 
        // library and then it will become a composer dependency
        if ($services->has('DbManager')) {
        	$adapter = $services->get('DbManager')->getAdapter();
            
        } else {
        	$config = $services->get('config');
	    	if (empty($config['currency_exchange_settings'])) {
	            throw new \Exception('Error reading currency exchange settings', 1);
	        }
	        $settings = $config['currency_exchange_settings'];

        	// check if has it's own connection
        	if (!empty($settings['db'])) {
                $adapter = new \Zend\Db\Adapter\Adapter($settings['db']);
            
            // check for global instance connection
            } elseif ($services->has('Zend\Db\Adapter\Adapter')) {
                $adapter = $services->get('Zend\Db\Adapter\Adapter');
            
            // check for global connection config
            } elseif(!empty($config['db'])) {
                $adapter = new \Zend\Db\Adapter\Adapter($config['db']);
                
            } else {
	            throw new \Exception('Could not create adapter for currency exchange', 1);
	        }
        }
        
        return $adapter;
    }
}
