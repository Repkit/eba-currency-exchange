<?php
namespace MicroIceExchangeRate\V1\Rest\Exchange;

use Zend\Db\TableGateway\TableGateway;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Expression;
use MicroIceExchangeRate\V1\Rest\Currencies\CurrenciesEntity;
use MicroIceExchangeRate\V1\Rest\Currencies\CurrenciesModel;


class ExchangeModel extends TableGateway
{
    /**
     * @var string
     */
	protected $entityClass = \MicroIceExchangeRate\V1\Rest\Exchange\ExchangeEntity::class;
    
    /**
     * @var string
     */
	protected $tableName = 'currency_exchange';
    
    /**
     * @var string
     */
	protected $primaryKey = 'Id';
    
    /**
     * @var \MicroIceExchangeRate\V1\Rest\Currencies\CurrenciesModel
     */
    private $currenciesModel;
    
    /**
     * @var string
     */
    private $errorMessage;

    
    
	public function __construct(\Zend\Db\Adapter\AdapterInterface $adapter, CurrenciesModel $currenciesModel, array $options = array()) {
    	// overwrite table name
    	if (!empty($options['tableName'])) {
    		$this->tableName = $options['tableName'];
    	}

    	// overwrite primaryKey
    	if (!empty($options['primaryKey'])) {
    		$this->primaryKey = $options['primaryKey'];
    	}

    	// create result set prototype
        $entityClass = $this->entityClass;
        $resultSetPrototype = new ResultSet();
        $resultSetPrototype->setArrayObjectPrototype(new $entityClass());

        // dependency injections
        $this->currenciesModel = $currenciesModel;
        
        $this->errorMessage = '';

        parent::__construct($this->tableName, $adapter, null, $resultSetPrototype);
    }
    
    
    /**
     * Return a result set filtered/sorted based on $params.
     */
    public function getBy($Storage = array(), $Where = null, $PostWhere = null)
    {
        $select = $this->preselect($Storage, $Where);

        $select->columns(array('Id', 'Rate', 'Status', 'Timestamp'));

        if (!empty($Where)) {
            $select = \TBoxDbFilter\DbFilter::withWhere($select, $Where, $this->tableName);
        }

        if (!empty($PostWhere)) {
            $select->where($PostWhere);
        }

        $sql = new Sql($this->Adapter);

        $statement = $sql->prepareStatementForSqlObject($select);
        $resultSet = new ResultSet();
        $resultSet->initialize($statement->execute());

        return $resultSet;
    }
    
    /**
     * Return the exchange rate between two currencies.
     * 
     * @param integer|string $baseCurrencyIdOrCode
     * @param integer|string $targetCurrencyIdOrCode
     * @return array|\ArrayObject|null
     */
    public function getCurrentRate($baseCurrencyIdOrCode, $targetCurrencyIdOrCode)
    {
        $this->errorMessage = '';
        
        $currenciesTable = $this->currenciesModel->getTable();
        $exchangeTable = $this->tableName;
        
    	if (empty($baseCurrencyIdOrCode) || empty($targetCurrencyIdOrCode)) {
            // both must be provided to be able to get an exchange rate
            $this->errorMessage = 'Both base currency and target currency must be provided';
    		return null;
    	}
        
        $select = $this->getSql()->select();
        
        $select->join(
                array('BaseCurrency' => $currenciesTable),
                $exchangeTable . '.BaseCurrencyId = BaseCurrency.Id',
                array('From' => 'Code')
        );
        $select->join(
                array('TargetCurrency' => $currenciesTable),
                $exchangeTable . '.TargetCurrencyId = TargetCurrency.Id',
                array('To' => 'Code')
        );
        
        // build the Where condition based on Id or Code, whichever is provided
        if (preg_match('/^[A-Z]{3}$/i', $baseCurrencyIdOrCode)) {
            $select->where(array('BaseCurrency.Code' => $baseCurrencyIdOrCode));
        } else {
            $select->where(array('BaseCurrencyId' => $baseCurrencyIdOrCode));
        }
        if (preg_match('/^[A-Z]{3}$/i', $targetCurrencyIdOrCode)) {
            $select->where(array('TargetCurrency.Code' => $targetCurrencyIdOrCode));
        } else {
            $select->where(array('TargetCurrencyId' => $targetCurrencyIdOrCode));
        }
        
        $select->where(array(
            $exchangeTable . '.Status' => ExchangeEntity::STATUS_ENABLED,
            'BaseCurrency.Status' => CurrenciesEntity::STATUS_ENABLED,
            'TargetCurrency.Status' => CurrenciesEntity::STATUS_ENABLED,
        ));
        
        $select->order($exchangeTable . '.Timestamp DESC');
        
        $select->limit(1);
        
        $result = $this->selectWith($select)->current();
        
        // if there is no result, check to see if any of the currencies has been marked as disabled, to return a relevant message
        if (!$result) {
            $baseCurrency = $this->currenciesModel->getOneByIdOrCode($baseCurrencyIdOrCode);
            if ($baseCurrency && $baseCurrency->Status == CurrenciesEntity::STATUS_DISABLED) {
                $this->errorMessage = 'Retrieving exchange rate for currency ' . $baseCurrency->Code . ' is not allowed';
                return null;
            }
            $targetCurrency = $this->currenciesModel->getOneByIdOrCode($targetCurrencyIdOrCode);
            if ($targetCurrency && $targetCurrency->Status == CurrenciesEntity::STATUS_DISABLED) {
                $this->errorMessage = 'Retrieving exchange rate for currency ' . $targetCurrency->Code . ' is not allowed';
                return null;
            }
        }
        
        return $result;
    }
    
    
    
    /**
     * Create a new exchange record between two currencies.
     * The currencies have to exist in database, if they are not not found, an error will be raised.
     * This function needs to be used for user input created rates.
     * 
     * @param integer|string $baseCurrencyIdOrCode
     * @param integer|string $targetCurrencyIdOrCode
     * @param integer|float|string $rate
     * @return integer
     */
    public function createRate($baseCurrencyIdOrCode, $targetCurrencyIdOrCode, $rate)
    {
        $this->errorMessage = '';
        
        // get base and target currency records
        $baseCurrency = $this->currenciesModel->getOneByIdOrCode($baseCurrencyIdOrCode);
        $targetCurrency = $this->currenciesModel->getOneByIdOrCode($targetCurrencyIdOrCode);
        
        if (!$baseCurrency) {
            $this->errorMessage = 'Unknown base currency';
            return 0;
        }
        if (!$targetCurrency) {
            $this->errorMessage = 'Unknown target currency';
            return 0;
        }
        if ($baseCurrency->Status == CurrenciesEntity::STATUS_DISABLED) {
            $this->errorMessage = 'Base currency has been disabled';
            return 0;
        }
        if ($targetCurrency->Status == CurrenciesEntity::STATUS_DISABLED) {
            $this->errorMessage = 'Target currency has been disabled';
            return 0;
        }
        if ($baseCurrency == $targetCurrency) {
            $this->errorMessage = 'Base currency and target currency cannot be the same';
            return 0;
        }
        
        // set disabled other exchange rates between the same currencies
        $this->update(array('Status' => ExchangeEntity::STATUS_DISABLED), array('BaseCurrencyId' => $baseCurrency->Id, 'TargetCurrencyId' => $targetCurrency->Id));
        
        $data = array(
            'BaseCurrencyId' => $baseCurrency->Id,
            'TargetCurrencyId' => $targetCurrency->Id,
            'Rate' => $rate,
            'Status' => ExchangeEntity::STATUS_ENABLED,
        );
        return $this->insert($data);
    }
    
    
    
    /**
     * Create a new exchange record between two currencies.
     * If the currency codes do not exist in database, they will be created.
     * This function does not check if the provided currency codes are valid, or if they exists and are disabled.
     * 
     * @param string $baseCurrencyCode
     * @param string $targetCurrencyCode
     * @param integer|float|string $rate
     * @return integer
     */
    public function createRateAndCurrencies($baseCurrencyCode, $targetCurrencyCode, $rate)
    {
        $this->errorMessage = '';
        
        $baseCurrency = $this->currenciesModel->getOneByIdOrCode($baseCurrencyCode);
        $targetCurrency = $this->currenciesModel->getOneByIdOrCode($targetCurrencyCode);

        // create currencies if needed
        if (!$baseCurrency) {
            $baseCurrencyId = $this->currenciesModel->createCurrency($baseCurrencyCode);
            if ($this->currenciesModel->hasError()) {
                $this->errorMessage = $this->currenciesModel->getErrorMessage();
                return 0;
            }
        } else {
            $baseCurrencyId = $baseCurrency->Id;
        }
        
        if (!$targetCurrency) {
            $targetCurrencyId = $this->currenciesModel->createCurrency($targetCurrencyCode);
            if ($this->currenciesModel->hasError()) {
                $this->errorMessage = $this->currenciesModel->getErrorMessage();
                return 0;
            }
        } else {
            $targetCurrencyId = $targetCurrency->Id;
        }
        
        // set disabled other exchange rates between the same currencies
        $this->update(array('Status' => ExchangeEntity::STATUS_DISABLED), array('BaseCurrencyId' => $baseCurrencyId, 'TargetCurrencyId' => $targetCurrencyId));
        
        // create exchange record
        $data = array(
            'BaseCurrencyId' => $baseCurrencyId,
            'TargetCurrencyId' => $targetCurrencyId,
            'Rate' => $rate,
            'Status' => ExchangeEntity::STATUS_ENABLED,
        );
        return $this->insert($data);
    }
    
    
    
    /**
     * Mark as deleted exchange rates for all, base currency or both base currency and target currency.
     * 
     * @param integer|string $baseCurrencyIdOrCode
     * @param integer|string $targetCurrencyIdOrCode
     * @return integer
     */
    public function deleteRate($baseCurrencyIdOrCode, $targetCurrencyIdOrCode = null)
    {
        $this->errorMessage = '';
        
        $now = date('Y-m-d H:i:s');
        
        // if target currency is provided, retrieve entity
        if ($targetCurrencyIdOrCode) {
            $targetCurrency = $this->currenciesModel->getOneByIdOrCode($targetCurrencyIdOrCode);
            if (!$targetCurrency) {
                $this->errorMessage = 'Unknown target currency';
                return 0;
            }
        }
        
        if ($baseCurrencyIdOrCode === '*') {
            if ($targetCurrencyIdOrCode) {
                // delete all records that have the given target currency
                $result = $this->update(
                        array('Status' => ExchangeEntity::STATUS_DELETED, 'Timestamp' => $now),                      // updated columns
                        array('TargetCurrencyId' => $targetCurrency->Id, 'Status' => ExchangeEntity::STATUS_ENABLED) // where condition
                );
                
            } else {
                // delete everything
                $result = $this->update(array('Status' => ExchangeEntity::STATUS_DELETED, 'Timestamp' => $now), array('Status' => ExchangeEntity::STATUS_ENABLED));
            }
            
        } else {
            // a specific base currency has been provided
            $baseCurrency = $this->currenciesModel->getOneByIdOrCode($baseCurrencyIdOrCode);
            if (!$baseCurrency) {
                $this->errorMessage = 'Unknown base currency';
                return 0;
            }
            
            if ($targetCurrencyIdOrCode) {
                // delete all rates between the two currencies
                $result = $this->update(
                        array('Status' => ExchangeEntity::STATUS_DELETED, 'Timestamp' => $now),
                        array('BaseCurrencyId' => $baseCurrency->Id, 'TargetCurrencyId' => $targetCurrency->Id, 'Status' => ExchangeEntity::STATUS_ENABLED)
                );
                
            } else {
                // delete all records that have the given base currency
                $result = $this->update(
                        array('Status' => ExchangeEntity::STATUS_DELETED, 'Timestamp' => $now),
                        array('BaseCurrencyId' => $baseCurrency->Id, 'Status' => ExchangeEntity::STATUS_ENABLED)
                );
            }
        }
        
        return $result;
    }
    
    
    /**
     * Get the currency Code from Id.
     * 
     * @param integer|string $id
     * @return string|boolean
     */
    public function getCurrencyCodeFromId($id)
    {
        $this->errorMessage = '';
        
        $currency = $this->currenciesModel->getOneByIdOrCode($id);
        
        if ($currency) {
            return $currency->Code;
            
        } else {
            $this->errorMessage = 'Invalid currency id';
            return false;
        }
    }
    
    
    /**
     * Return TRUE if an error has occurred after the last operation.
     * 
     * @return boolean
     */
    public function hasError()
    {
        return !empty($this->errorMessage);
    }
    
    
    /**
     * Return the message for the error occurred after the last operation.
     * 
     * @return string
     */
    public function getErrorMessage()
    {
        return $this->errorMessage;
    }
    
    
    public function getEntityClass()
    {
    	return $this->entityClass;
    }
    
    
    private function preselect(array $Storage = array(),&$Where = null)
    {
        $select = new Select();
        $select
            ->from($this->tableName);

        if( !empty($Storage) )
        {
            $joins = $Storage['joins'];
            // add extra joins if they are defined
            foreach($joins as $type => $joincollection)
            {
                foreach($joincollection as $join)
                {
                    if(!empty($join['table']) && !empty($join['on']) && !empty($join['columns']))
                    {
                        $select->join($join['table']
                            , new Expression($join['on'])
                            , $join['columns']
                            , $type
                        );
                    }
                    
                }
            }

            if( isset($Where) && !empty($Where) 
                && isset($Storage['where']) && !empty($Storage['where']) 
                && isset($Storage['where']['external_columns']) && !empty($Storage['where']['external_columns']) )
            {
                $externalColumns = $Storage['where']['external_columns'];
                $externalWhere = array();
                foreach ($Where as $key => $condition) 
                {
                    $propertyName = $condition['name'];
                    if( isset($externalColumns[$propertyName]) )
                    {
                        $externalWhere[$key] = $condition;
                        $externalWhere[$key]['name'] = $externalColumns[$propertyName];
                        unset($Where[$key]);
                    }
                }
                if( !empty($externalWhere) ){
                    $select = \TBoxDbFilter\DbFilter::withWhere($select, $externalWhere);
                }
            }
        }

        return $select;
    }

}