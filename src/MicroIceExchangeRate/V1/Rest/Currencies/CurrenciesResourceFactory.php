<?php
namespace MicroIceExchangeRate\V1\Rest\Currencies;

class CurrenciesResourceFactory
{
    public function __invoke($services)
    {
    	$model = $services->get(\MicroIceExchangeRate\V1\Rest\Currencies\CurrenciesModel::class);
        
        $config = $services->get('config');
        $settings = $config['currency_exchange_settings'];

        return new CurrenciesResource($model, $settings);
    }
}
