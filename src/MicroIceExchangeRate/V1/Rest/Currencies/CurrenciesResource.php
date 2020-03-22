<?php
namespace MicroIceExchangeRate\V1\Rest\Currencies;

use ZF\ApiProblem\ApiProblem;
use ZF\Rest\AbstractResourceListener;
use Zend\Db\TableGateway\TableGatewayInterface;


class CurrenciesResource extends AbstractResourceListener
{
    /** @var CurrenciesModel */
    private $model;
    
    /** @var array */
    private $settings;

    
    public function __construct(TableGatewayInterface $Model, array $Settings)
    {
        $this->model = $Model;
        $this->settings = $Settings;
    }
    
    
    /**
     * Create a resource
     *
     * @param  mixed $data
     * @return ApiProblem|mixed
     */
    public function create($data)
    {
        return new ApiProblem(405, 'The POST method has not been defined');
    }

    /**
     * Delete a resource
     *
     * @param  mixed $id
     * @return ApiProblem|mixed
     */
    public function delete($id)
    {
        return new ApiProblem(405, 'The DELETE method has not been defined for individual resources');
    }

    /**
     * Delete a collection, or members of a collection
     *
     * @param  mixed $data
     * @return ApiProblem|mixed
     */
    public function deleteList($data)
    {
        return new ApiProblem(405, 'The DELETE method has not been defined for collections');
    }

    /**
     * Fetch a resource
     *
     * @param  mixed $id
     * @return ApiProblem|mixed
     */
    public function fetch($id)
    {
        try {
            // $id can be numeric (Id), three letter currency code (Code) or keyword 'enabled'
            if ($id === 'enabled') {
                return $this->fetchAll(['filter' => ['Status' => CurrenciesEntity::STATUS_ENABLED]]);
                
            } else {
                $currency = $this->model->getOneByIdOrCode($id);
            }
            
            return $currency;
            
        } catch(\Exception $e) {
            return new ApiProblem(417, $e->getMessage());
        }
    }

    /**
     * Fetch all or a subset of resources
     *
     * @param  array $params
     * @return ApiProblem|mixed
     */
    public function fetchAll($params = [])
    {
        try {
            // object to array transformation
            if(is_object($params)){
                $params = get_object_vars($params);
            }
            
            $storage = array();
            if (!empty($this->settings['currencies']['storage']['joins'])) {
                $storage = $this->settings['currencies']['storage'];
            }

            $dbfilterwhere = array();
            if (!empty($params['filter'])) {
                $dbfilterwhere = $params['filter'];
            }
            
            $currencies = $this->model->getBy($storage, $dbfilterwhere);
            
            return new CurrenciesCollection(new \Zend\Paginator\Adapter\Iterator($currencies));

        } catch(\Exception $e) {
            $status = $e->getCode() ? $e->getCode() : 417;
            return new ApiProblem($status, $e->getMessage());
        }
    }

    /**
     * Patch (partial in-place update) a resource
     *
     * @param  mixed $id
     * @param  mixed $data
     * @return ApiProblem|mixed
     */
    public function patch($id, $data)
    {
        try {
            // stdClass to array transformation
            if(is_object($data)){
                $data = get_object_vars($data);
            }
            
            // check if status value is valid
            if (isset($data['Status']) && !in_array($data['Status'], array(CurrenciesEntity::STATUS_ENABLED, CurrenciesEntity::STATUS_DISABLED))) {
                throw new \Exception('Invalid status specified for update', 400);
            }
            
            $this->model->updateCurrency($id, $data);
            
            if ($this->model->hasError()) {
                throw new \Exception($this->model->getErrorMessage(), 400);
            }
        
        } catch(\Exception $e) {
            $status = $e->getCode() ? $e->getCode() : 417;
            return new ApiProblem($status, $e->getMessage());
        }        
    }

    /**
     * Patch (partial in-place update) a collection or members of a collection
     *
     * @param  mixed $data
     * @return ApiProblem|mixed
     */
    public function patchList($data)
    {
        return new ApiProblem(405, 'The PATCH method has not been defined for collections');
    }

    /**
     * Replace a collection or members of a collection
     *
     * @param  mixed $data
     * @return ApiProblem|mixed
     */
    public function replaceList($data)
    {
        return new ApiProblem(405, 'The PUT method has not been defined for collections');
    }

    /**
     * Update a resource
     *
     * @param  mixed $id
     * @param  mixed $data
     * @return ApiProblem|mixed
     */
    public function update($id, $data)
    {
        return new ApiProblem(405, 'The PUT method has not been defined for individual resources');
    }
}
