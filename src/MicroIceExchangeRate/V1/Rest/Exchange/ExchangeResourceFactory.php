<?php
namespace MicroIceExchangeRate\V1\Rest\Exchange;

class ExchangeResourceFactory
{
    public function __invoke($services)
    {
        try {
            // requires OpenExchangeRates and AdminMailer
            $config = $services->get('config');
            $settings = $config['currency_exchange_settings'];
            $openExchangeRates = new \MicroIceExchangeRate\Services\OpenExchangeRates($settings);
            $adminMailer = new \MicroIceExchangeRate\Services\AdminMailer($settings);

            $model = $services->get(\MicroIceExchangeRate\V1\Rest\Exchange\ExchangeModel::class);
            return new ExchangeResource($model, $openExchangeRates, $adminMailer, $settings);
            
        } catch(\Exception $e) {
            var_dump(MAIL_URL);die();
            die($e->getMessage());
        }
    }
}
