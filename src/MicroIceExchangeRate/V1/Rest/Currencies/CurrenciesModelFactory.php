<?php
namespace MicroIceExchangeRate\V1\Rest\Currencies;


class CurrenciesModelFactory
{
    use \MicroIceExchangeRate\Traits\DetectAdapter;
    
	public function __invoke($services)
    {
        return new CurrenciesModel($this->getAdapter($services));
    }
}