<?php
namespace MicroIceExchangeRate\V1\Rest\Exchange;


class ExchangeModelFactory
{
    use \MicroIceExchangeRate\Traits\DetectAdapter;
    
	public function __invoke($services)
    {
        // we need to call some methods from CurrenciesModel in ExchangeModel, therefor inject it into constructor
        $currenciesModelFactory = new \MicroIceExchangeRate\V1\Rest\Currencies\CurrenciesModelFactory;
        $currenciesModel = $currenciesModelFactory($services);
        
        return new ExchangeModel($this->getAdapter($services), $currenciesModel);
    }
}