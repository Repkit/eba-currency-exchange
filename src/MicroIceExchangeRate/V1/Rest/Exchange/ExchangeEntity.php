<?php
namespace MicroIceExchangeRate\V1\Rest\Exchange;

class ExchangeEntity implements \JsonSerializable
{
    CONST STATUS_ENABLED  = 1;
    CONST STATUS_DISABLED = 0;
    CONST STATUS_DELETED = 99;
	
	public $Id;
	public $Rate;
	public $Status;
	public $Timestamp;
    
    // `Code` column from currencies table
    // we need to add these to return them along with the currencies Id
    public $From;
    public $To;

    
	public function jsonSerialize()
    {
        return json_encode($this->getArrayCopy());
    }
    

    public function getArrayCopy()
    {
        $data = (array)$this;
        
        foreach ($data as $key => $value) {
        	if (!isset($value)) {
        		unset($data[$key]);
        	}
        }

        return $data;
    }

    
    public function exchangeArray($array) 
    {
        foreach (self::getDBFieldNames() as $field) {
            $this->$field = isset($array[$field]) ? $array[$field] : null;
        }
    }
    
    
    /**
     * Get complete list of db field names that were declared as class properties
     * 
     * @return array
     */
    public static function getDBFieldNames()
    {
        $class = new \ReflectionClass(self::class);
        $properties = $class->getProperties();
        $dbFieldNames = array();
        foreach ($properties as $property) {
            /* @var $property \ReflectionProperty */
            $dbFieldNames[] = $property->getName();
        }
        return $dbFieldNames;
    }
}
