<?php
namespace MicroIceExchangeRate\Services;


/**
* Wrapper for Openexchangerates.org API
 */
class OpenExchangeRates
{
    /**
     * @var string
     */
    private $appId;

    
    function __construct($settings)
    {
        if (empty($settings['openexchangerates_app_id'])) {
            throw new \Exception('MicroIceExchangeRate openexchangerates_app_id not set');
        }
        $this->appId = $settings['openexchangerates_app_id'];
    }
    
    
    /**
     * Call "convert" endpoint
     * 
     * @param string $baseCurrencyCode
     * @param string $targetCurrencyCode
     * @return float|boolean
     */
    public function convert($baseCurrencyCode, $targetCurrencyCode)
    {
        $options = array('adapter' => 'Zend\Http\Client\Adapter\Curl');
        $client = new \Zend\Http\Client('https://openexchangerates.org/api/convert/1/' . $baseCurrencyCode . '/' . $targetCurrencyCode . '?app_id=' . $this->appId, $options);
        $response = $client->send();
        if ($response->getStatusCode() !== 200) {
            return false;
        }
        $content = json_decode($response->getContent(), true);
        return $content['response'];
    }
}
