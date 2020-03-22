<?php
namespace MicroIceExchangeRate\V1\Rest\Currencies;

use Zend\Db\TableGateway\TableGateway;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Expression;


class CurrenciesModel extends TableGateway
{
    /**
     * @var string
     */
	protected $entityClass = \MicroIceExchangeRate\V1\Rest\Currencies\CurrenciesEntity::class;
    
    /**
     * @var string
     */
	protected $tableName = 'currencies';
    
    /**
     * @var string
     */
	protected $primaryKey = 'Id';
    
    /**
     * @var string
     */
    private $errorMessage;

    
	public function __construct(\Zend\Db\Adapter\AdapterInterface $adapter, array $options = array())
    {
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

        $this->errorMessage = '';

        parent::__construct($this->tableName, $adapter, null, $resultSetPrototype);
    }
    

    /**
     * Get a record based on Id or Code
     * 
     * @param integer|string $id
     * @return CurrenciesEntity
     */
    public function getOneByIdOrCode($id)
    {
        $this->errorMessage = '';
        
    	if (empty($id)) {
            $this->errorMessage = 'Id or code required but not provided';
    		return false;
    	}
        
        $select = $this->getSql()->select()
                ->where(array('Id' => $id))
                ->where(array('Code' => $id), \Zend\Db\Sql\Predicate\PredicateSet::OP_OR)
        ;
        return $this->selectWith($select)->current();
    }
    
    
    /**
     * Return a result set filtered/sorted based on $params.
     */
    public function getBy($Storage = array(), $Where = null, $PostWhere = null)
    {
        $select = $this->preselect($Storage, $Where);

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
     * Update Status field for one record
     * 
     * @param string|integer $id
     * @param integer $status
     * @return int
     */
    public function updateStatus($id, $status)
    {
        $this->errorMessage = '';
        
        // check if currency exists
        $currency = $this->getOneByIdOrCode($id);
        if (!$currency) {
            $this->errorMessage = 'Unknown currency';
            return 0;
        }
        
        return $this->update(array(
            'Status' => $status,
            'Timestamp' => date('Y-m-d H:i:s')
        ), array('Id' => $currency->Id));
    }
    
    
    /**
     * Update currency record
     * 
     * @param string|integer $id
     * @param array $Data
     * @return int
     */
    public function updateCurrency($Id, array $Data)
    {
        $data = $Data;
        
        $this->errorMessage = '';
        
        // check if currency exists
        $currency = $this->getOneByIdOrCode($Id);
        if (!$currency) {
            $this->errorMessage = 'Unknown currency';
            return 0;
        }
        
        // if default is changed to 1, reset all other defaults to 0
        if (!empty($Data['Default'])) {
            $this->update(array('Default' => 0), array('Default' => 1));
        }
        
        $data['Timestamp'] = date('Y-m-d H:i:s');
        
        return $this->update($data, array('Id' => $currency->Id));
    }
    
    
    /**
     * Create a new currency and return its Id
     * 
     * @param string $code
     * @param string|null $name
     * @return int
     */
    public function createCurrency($code, $name = null)
    {
        $this->errorMessage = '';
        
        // check if currency exists
        if ($this->getOneByIdOrCode($code)) {
            $this->errorMessage = 'Currency already exists';
            return 0;
        }
        // do insert
        $this->insert(array(
            'Code' => $code,
            'Name' => $name,
            'Status' => CurrenciesEntity::STATUS_ENABLED,
        ));
        
        return $this->getLastInsertValue();
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