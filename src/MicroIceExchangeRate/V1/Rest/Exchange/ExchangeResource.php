<?php
namespace MicroIceExchangeRate\V1\Rest\Exchange;

use ZF\ApiProblem\ApiProblem;
use ZF\Rest\AbstractResourceListener;
use Zend\Db\TableGateway\TableGatewayInterface;
use MicroIceExchangeRate\V1\Rest\Exchange\ExchangeModel;
use MicroIceExchangeRate\Services\OpenExchangeRates;
use MicroIceExchangeRate\Services\AdminMailer;


class ExchangeResource extends AbstractResourceListener
{
    /** @var ExchangeModel */
    private $model;
    
    /** @var OpenExchangeRates */
    private $openExchangeRates;
    
    /** @var AdminMailer */
    private $adminMailer;
    
    /** @var array */
    private $settings;
    
    
    public function __construct(TableGatewayInterface $model, OpenExchangeRates $openExchangeRates, AdminMailer $adminMailer, array $Settings)
    {
        $this->model = $model;
        $this->openExchangeRates = $openExchangeRates;
        $this->adminMailer = $adminMailer;
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
        try {
            // stdClass to array transformation
            if(is_object($data)){
                $data = get_object_vars($data);
            }
            
            // extract base currency Id/Code and target currency Id/Code (optional) from route
            $e = $this->getEvent();
            $route = $e->getRouteMatch();
            $baseCurrencyIdOrCode = $route->getParam('currency_id_1');
            $targetCurrencyIdOrCode = $route->getParam('currency_id_2');
            
            if (empty($baseCurrencyIdOrCode) || empty($targetCurrencyIdOrCode)) {
                throw new \Exception('Both base and target currencies must be provided', 400);
            }
            
            // only Rate (or Value for backward compatibility) must be provided
            if (!isset($data['Rate']) && !isset($data['Value'])) {
                throw new \Exception('Invalid field(s) specified for create, "Rate" is expected to be provided', 400);
            }
            
            $rate = isset($data['Rate']) ? $data['Rate'] : $data['Value'];
            
            $this->model->createRate($baseCurrencyIdOrCode, $targetCurrencyIdOrCode, $rate);
            
            if ($this->model->hasError()) {
                throw new \Exception($this->model->getErrorMessage(), 400);
            }
            
        } catch(\Exception $e) {    
            $status = $e->getCode() ? $e->getCode() : 417;
            return new ApiProblem($status, $e->getMessage());
        }
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
        try {
            // extract base currency Id/Code and target currency Id/Code (optional) from route
            $e = $this->getEvent();
            $route = $e->getRouteMatch();
            $baseCurrencyIdOrCode = $route->getParam('currency_id_1');
            $targetCurrencyIdOrCode = $route->getParam('currency_id_2');

            $this->model->deleteRate($baseCurrencyIdOrCode, $targetCurrencyIdOrCode);
            
            if ($this->model->hasError()) {
                throw new \Exception($this->model->getErrorMessage(), 400);
            }
            
            return TRUE;
        
        } catch(\Exception $e) {
            $status = $e->getCode() ? $e->getCode() : 417;
            return new ApiProblem($status, $e->getMessage());
        }
    }

    /**
     * Fetch a resource
     *
     * @param  mixed $id
     * @return ApiProblem|mixed
     */
    public function fetch($id)
    {
        return new ApiProblem(405, 'The GET method has not been defined for individual resources');
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
            
            // extract base currency Id/Code and target currency Id/Code (optional) from route
            $e = $this->getEvent();
            $route = $e->getRouteMatch();
            $baseCurrencyIdOrCode = $route->getParam('currency_id_1');
            $targetCurrencyIdOrCode = $route->getParam('currency_id_2');
            
            // if target currency Id or Code is provided, we return a single item with the current exchange rate between the two currencies
            // in this case $baseCurrencyIdOrCode cannot be all (*) !
            if ($targetCurrencyIdOrCode) {
                $result = $this->model->getCurrentRate($baseCurrencyIdOrCode, $targetCurrencyIdOrCode);
                if ($this->model->hasError()) {
                    throw new \Exception($this->model->getErrorMessage(), 400);
                }
                // if the exchange rate is from today, return it
                if ($result && $result->Timestamp >= (new \DateTime())->format('Y-m-d 00:00:00')) {
                    return $result;
                }
                // If record does not exist, it can be from one of the following reasons:
                //      - one or both currencies do not exist in database
                //      - one or both currencies have been disabled
                //      - no exchange record exists or all have been marked as deleted
                // If an exchange record does exist but it is older than today, we also need to create a new record

                // to call OpenExchangeRates service, we need to use currency codes, so if numeric Ids have been provided,
                // we need to get the Code from Id
                $baseCurrencyCode = preg_match('/^[A-Z]{3}$/i', $baseCurrencyIdOrCode) ? $baseCurrencyIdOrCode : $this->model->getCurrencyCodeFromId($baseCurrencyIdOrCode);
                $targetCurrencyCode = preg_match('/^[A-Z]{3}$/i', $targetCurrencyIdOrCode) ? $targetCurrencyIdOrCode : $this->model->getCurrencyCodeFromId($targetCurrencyIdOrCode);
                if (!$baseCurrencyCode || !$targetCurrencyCode) {
                    throw new \Exception('Invalid currency id', 400);
                }
                
                // make the call to the OpenExchangeRates API using the currency codes
                $response = $this->openExchangeRates->convert($baseCurrencyCode, $targetCurrencyCode);
                
                if ($response === false) {
                    // if failed to retrieve the rate from openexchange rates
                    if ($result) {
                        // if there is an older exchange rate, return that
                        return $result;
                    } else {
                        // notify admin that exchange rate cannot be retrieved
                        $this->adminMailer->send('An error has occured', 'Failed to retrieve the exchange rate from ' . $baseCurrencyCode . ' to ' . $targetCurrencyCode);
                        throw new \Exception('Cannot retrieve exchange rate for this currency', 400);
                    }
                }
                // exchange rate succesfully retrieved
                $rate = $response;
                
                $this->model->createRateAndCurrencies($baseCurrencyCode, $targetCurrencyCode, $rate);
                if ($this->model->hasError()) {
                    throw new \Exception($this->model->getErrorMessage(), 400);
                }
                
                // now retrieve the newly created record
                $result = $this->model->getCurrentRate($baseCurrencyCode, $targetCurrencyCode);
                if ($this->model->hasError()) {
                    throw new \Exception($this->model->getErrorMessage(), 400);
                }
                
                return $result;
            }

            // show all exchange rates for a given base currency
            // if "all" is specified, return everyting, otherwise set the base currency as filter
            
            $storage = array();
            if (!empty($this->settings['currency_exchange']['storage']['joins'])) {
                $storage = $this->settings['currency_exchange']['storage'];
            }

            $dbfilterwhere = array();
            if (!empty($params['filter'])) {
                $dbfilterwhere = $params['filter'];
            }
            
            if ($baseCurrencyIdOrCode !== '*') {
                $index = count($dbfilterwhere);
                if (preg_match('/^[A-Z]{3}$/i', $baseCurrencyIdOrCode)) {
                    $dbfilterwhere[$index]['name'] = 'From';
                } else {
                    $dbfilterwhere[$index]['name'] = 'BaseCurrencyId';
                }
                $dbfilterwhere[$index]['term'] = $baseCurrencyIdOrCode;
            }
            
            // default sort by Timestamp desc
            // if no sort order provided in filters
            $sortParams = FALSE;
            foreach ($dbfilterwhere as $index => $element) {
                if (isset($element['direction'])) {
                    $sortParams = TRUE;
                    break;
                }
            }
            if (!$sortParams) {
                $index = count($dbfilterwhere);
                $dbfilterwhere[$index]['name'] = 'Timestamp';
                $dbfilterwhere[$index]['direction'] = 'desc';
            }
            
            $exchange = $this->model->getBy($storage, $dbfilterwhere);
            
            return new ExchangeCollection(new \Zend\Paginator\Adapter\Iterator($exchange));

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
        return new ApiProblem(405, 'The PATCH method has not been defined for individual resources');
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
