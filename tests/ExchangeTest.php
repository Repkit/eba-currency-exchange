<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace MicroIceExchangeRate;


use PHPUnit_Framework_TestCase as TestCase;
use ApplicationTest\Bootstrap;
use Zend\Db\Sql\Select;
use TBoxCurrencyConverter\CurrencyConverter;

/**
 * Description of AuthenticationTest
 *
 * @author Alex
 */
class ExchangeTest extends TestCase
{
    private static $Client = APP_URL;
    private $serviceManager;           
     
   /**
    * Set up
    */
    public function setUp()
    {
    	$this->markTestSkipped('The URL is not available.');
        $this->serviceManager = Bootstrap::getServiceManager();
    }
    
    /**
     * Route parameters used to test common properties of both requests
     * @return type
     */
    public function requestParamsProvider() {
        return [
            ['currencies/all/exchange'],
            ['currencies/enabled']
        ];
    }    
    
    /**
     * Data provider to test the exchange between two currencies
     * @return type
     */
    public function currencyDataProvider() {
        return [
            ['USD', 'EUR'],
            ['EUR', 'RON'],
            ['TOP', 'RON'],
            ['SOS', 'SLL'],
            ['SOS', 'RON'],
            ['TND', 'TOP']
        ];
    }
    
    /**
     * Data used to test exchange rates filters
     * @return type
     */
    public function filterDataProviderForExchangeRates() {
        return [       
            ['From', 'USD'],
            ['To', 'EUR'],
            ['From', 'TJS'],
            ['Status', 1],
            ['Status', 0],
            ['Date', '2016-07-05'],
            ['Date', '2016-06-30'],                      
        ];
    }
     
    /**
     * Data used to test filters for currencies
     * @return type
     */
    public function filterDataProviderforCurrencies() {
        return [       
            ['Code', 'USD'],           
            ['Code', 'TJS'],
            ['Status', 1],
            ['Status', 0],                  
        ];
    }    
       
    /**
     * Data used to test the addition of exchange rate value
     * @return type
     */
    public function postDataProvider() {
        return [
            ['EUR', 'USD', '1.345'],
            ['SOS', 'RON', '0.111'],
        ];
    }               
    
    /**
     * Invalid data
     * @return type
     */
    public function postFailDataProvider() {
        return [
            ['EUR', null, '1.345'],
            [null, 'RON', '0.111'],
            ['EUR', 'RON', null],       
            ['EUR', 'RON', 'dsada'],
            ['EURO', 'RON', 'dsada'],
            ['EUR', 'RONI', 'dsada'],
        ];
    }
    
    /**
     * Invalid route parameters
     * @return type
     */
    public function routeParametersFailData() {
        return [
            [null],
            ['RONI'],
            ['wrong'],
            ['#$#'],
            ['EURO'],
        ]; 
    }    
    
    /**
     * Data used to test when a new currency is added
     * @return type
     */
    public function currrencyPostDataProvider() {
        return [
            ['TST', 'Test', '%$', 'Somewhere in the world'],
            ['TTS', 'Test', '##', 'Somewhere in the world']
        ];
    }
    
    /**
     * Data used to test currency fetch
     * @return type
     */
    public function fetchCurrencyDataProvider() {
        return [
            ["EUR"], 
            ["RON"],
            ["enabled"],
            ["TJS"]
        ];
    }
    
    /**
     * Data used to test the deletion of a currency
     * @return type
     */
    public function deleteDataProvider() {
        return [
            [461]                            
        ];
    }
    
    /**
     * Data used to test the status change
     * @return type
     */
    public function patchDataProviderForStatusChange() {
        return [
            [182],            
        ];
    }
     /**
     * Check currencies response
     * @return type
     */
    public function test_currenciesUrlAndResponse() {
        $requestUrl = self::$Client . '/currencies';
       
        // $requestUrl = self::$Client . "/" . $UrlParam;
        $ch = curl_init($requestUrl); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);     
   
        $this->assertEquals(200,$httpcode);   

        return array('requestUrl' => $requestUrl, 'response' => $response);
    }   
    
    /**
     * Check keys when results are paginated
     * @param type $Response
     */
    public function checkArrayHasKeyForPaginatedResults($Response) {
        $this->assertArrayHasKey('_embedded',$Response);
        $this->assertArrayHasKey('_links', $Response);
        $this->assertArrayHasKey('page_count', $Response);
        $this->assertArrayHasKey('page_size', $Response);
        $this->assertArrayHasKey('total_items', $Response);
    }
    
    /**
     * Currencies response check
     * @depends test_currenciesUrlAndResponse
     */
    public function test_getCurrencies(Array $RequestUrlAndResponseData) {        
        $requestUrl = $RequestUrlAndResponseData['requestUrl'];
    
        $response = $RequestUrlAndResponseData['response'];
        $response = json_decode($response,TRUE);

        //check response is not false
        $this->assertTrue($response !== FALSE);       
        $embedded = $response['_embedded'];

        //check response has currencies key and is not empty
        $this->assertArrayHasKey('currencies',$embedded);
        $this->assertNotEmpty($embedded['currencies']);
        $currencies = $embedded['currencies'];
        $currency = reset($currencies);

        //check this currency has the mandatory properties set
        $this->assertArrayHasKey('Code',$currency);
        $this->assertArrayHasKey('Name',$currency);
        $this->assertArrayHasKey('Symbol',$currency);                        
        
        $code = $currency['Code'];
        $name = $currency['Name'];
        $symbol = $currency['Symbol'];
        $timestamp = $currency['Timestamp'];
        $status = $currency['Status'];
        
        $this->assertNotNull($code);
        $this->assertNotNull($name);
        $this->assertNotNull($symbol);
        $this->assertNotNull($timestamp);
        $this->assertNotNull($status);
        
        return array('requestUrl' => $requestUrl, 'currency_code' => $code);                                               
    }         
    
    /**
     * @depends test_currenciesUrlAndResponse
     */    
    public function test_CurrenciesListHasKeys(Array $RequestUrlAndResponseData) {   
        $response = $RequestUrlAndResponseData['response'];
        $response = json_decode($response, TRUE);
       
        $this->checkArrayHasKeyForPaginatedResults($response);                
    }
    
    /**
     * Check goto page link
     * @depends test_currenciesUrlAndResponse     
     */    
    public function test_nextPage(Array $RequestUrlAndResponseData) {
        $response = json_decode($RequestUrlAndResponseData['response'], TRUE);
        $this->assertTrue($response !== FALSE);                   
        
        if ($this->assertGreaterThanOrEqual(1, $response['page_count'])) {
            foreach ($response['page_count'] as $page) {
                $requestUrl = self::$Client . '/currencies?page=' . $page;
                $ch = curl_init($requestUrl); 
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                $response = curl_exec($ch);
                $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);     
                $this->assertEquals(200,$httpcode);   
            }                                   
        }
    }
        
    /**
     * @depends test_getCurrencies
     */
    public function test_getCurrency(Array $Data) {
        $requestUrl = $Data['requestUrl'];
        $code = $Data['currency_code'];
        $requestUrl .=  '/' . $code;
        $ch = curl_init($requestUrl); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch); 
        $this->assertEquals(200,$httpcode);            
        $response = json_decode($response,TRUE);
        
        //check response is not false
        $this->assertTrue($response !== FALSE);

        //check this currency has the mandatory properties set
        $this->assertArrayHasKey('Code',$response);
        $this->assertArrayHasKey('Name',$response);
        $this->assertArrayHasKey('Symbol',$response);
        $this->assertArrayHasKey('Status', $response);
        $this->assertArrayHasKey('Timestamp', $response);
                       
        $responseCode = $response['Code'];               
       
        //check we receive the same code
        $this->assertEquals($code,$responseCode);
    }               
 
    /**
     * @dataProvider requestParamsProvider
     * @param type $requestParam
     */
    public function test_commonProperties($requestParam) {
        $requestUrl = self::$Client . '/' . $requestParam;
        $ch = curl_init($requestUrl); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);     
        $this->assertTrue($response !== FALSE);       
        $this->assertEquals(200,$httpcode);   
        
        $response = json_decode($response, TRUE);
        //should be paginated
        $this->checkArrayHasKeyForPaginatedResults($response);
        $embedded = $response['_embedded'];
         
        if ($requestParam == "currencies/all/exchange") {             
            $this->assertArrayHasKey('exchange',$embedded);
        }
        else {
            $this->assertArrayHasKey('items',$embedded);
            $this->assertNotEmpty($embedded['items']);
          
            foreach ($embedded['items'] as $element) {
                $this->assertEquals($element['Status'], 1);
            }                       
        }                   
        
        //if page count in greater than 1, check the request to the next page
        if ($response['page_count'] > 1) {
            foreach ($response['page_count'] as $page) {
                $requestUrl = self::$Client . $requestParam . '?page=' . $page;
                $ch = curl_init($requestUrl); 
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                $response = curl_exec($ch);
                $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);     
                $this->assertEquals(200,$httpcode);   
            }                                   
        }          
    }   
        
    /**
     * Exchange rates filters test
     * @dataProvider filterDataProviderForExchangeRates        
     */
    public function test_FilterForAllExchangeRates($Field, $Value) {                     
        $requestUrl = self::$Client . "/currencies/all/exchange" . "?filter[" . $Field . "]=" . $Value;         
             
        $ch = curl_init($requestUrl); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($ch);       
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);                   
        $response = json_decode($response, TRUE);                 
       
        $this->assertEquals(200,$httpcode);    
        $this->checkArrayHasKeyForPaginatedResults($response);        
    }   
    
    /**
     * Currencies filters test
     * @dataProvider filterDataProviderforCurrencies
     * @param type $Field
     * @param type $Value
     */
    public function test_FilterForCurrencies($Field, $Value) {
        $requestUrl = self::$Client . "/currencies" . "?filter[" . $Field . "]=" . $Value;         
         
        $ch = curl_init($requestUrl); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($ch);       
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);                   
        $response = json_decode($response, TRUE);                 
          
        $this->assertEquals(200,$httpcode);            
        $this->checkArrayHasKeyForPaginatedResults($response);        
    }
 
    /**
     * @dataProvider postDataProvider
     * @param type $From
     * @param type $To
     * @param type $Value
     */
    public function test_exchangePost($From, $To, $Value) {               
        if($this->serviceManager->has('MicroIceExchangeRate\V1\Rest\Exchange\Mapper\Db\Model\ExchangeMapper')){
            $model = $this->serviceManager->get('MicroIceExchangeRate\V1\Rest\Exchange\Mapper\Db\Model\ExchangeMapper');
            $date = date('Y-m-d');
            $model->updateStatus($From, $To, $date);
            $insertData = array(
                'From' => $From,  
                'To' => $To,
                'Value' => floatval($Value),
                'Status' => 1,
                'Date' => $date
            );

            $id = $model->save($insertData);
            $dbData = $model->fetchExchangeRow($From, $To, $date);
            foreach($dbData as $row){
                if($row['Status'] == 1){
                    $persistedData = $row;
                    $persistedData['Status'] = intval($persistedData['Status']);
                    $persistedData['Value'] = floatval($persistedData['Value']);
                    unset($persistedData['Id']);
                    unset($persistedData['Timestamp']);
                    break;
                }
            }
            $this->assertEquals($insertData,$persistedData);               
        }
    }
 
    /**
     * @dataProvider postFailDataProvider
     * @param type $From
     * @param type $To
     * @param type $Value
     */
    public function test_postFail($From, $To, $Value) 
    {                                
        $this->setExpectedException("\Exception");
        if($this->serviceManager->has('MicroIceExchangeRate\\V1\\Rest\\Exchange\\ExchangeResource')){
            $model = $this->serviceManager->get('MicroIceExchangeRate\\V1\\Rest\\Exchange\\ExchangeResource');

            $Data = array(
                'From' => $From,
                'To' => $To,
                'Value' => $Value
            );

            $model->create($Data); 
           }              
    }            
    
    /** 
     * Test invalid route parameters
     * @dataProvider routeParametersFailData
     */ 
    public function test_routeParameters($From) {
        if($this->serviceManager->has('MicroIceExchangeRate\V1\Rest\Exchange\Mapper\Db\Model\ExchangeMapper')){
            $model = $this->serviceManager->get('MicroIceExchangeRate\V1\Rest\Exchange\Mapper\Db\Model\ExchangeMapper');
                   
            $isValidFrom = $model->isValidParameter($From);            
            $this->assertFalse($isValidFrom);
        }       
    }    
      
    /** 
     * Delete a currency
     * DELETE
     * @dataProvider deleteDataProvider
     */
    public function test_deleteCurrency($Id) {
        $requestUrl = self::$Client . "/currencies/" . $Id;
        
        $ch = curl_init($requestUrl); 
        curl_setopt($ch, CURLOPT_URL,$requestUrl);   
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $this->assertEquals(204, $httpCode);                  
    }
    
        
    /**
     * Modify currency status
     * PATCH
     * @dataProvider patchDataProviderForStatusChange
     * @param type $Id
     */
    public function test_patchCurrency($Id) {     
        $requestUrl = self::$Client . "/currencies/" . $Id;
        $data = "{'Status':'1'}";
        $headers = array('Accept: application/json');
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $requestUrl);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);       
        $response = curl_exec($curl);   
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);       
        curl_close($curl);
            
        $this->assertEquals(200, $httpCode);                         
    }

    /**
     * Add a currency
     * CREATE
     * @dataProvider currrencyPostDataProvider
     * @param type $From
     * @param type $To
     * @param type $Value
     */
    public function test_currencyPost($Code, $Name, $Symbol, $Locations) {               
        if($this->serviceManager->has('MicroIceExchangeRate\V1\Rest\Currencies\Mapper\Db\Model\CurrenciesMapper')){
            $model = $this->serviceManager->get('MicroIceExchangeRate\V1\Rest\Currencies\Mapper\Db\Model\CurrenciesMapper');
            
            $data =(object)array(
               'Code' => $Code,
               'Name' => $Name,
               'Symbol' => $Symbol,
               'Locations' => $Locations,                
            );
                      
            $insertData = $model->buildCurrencyData($data);
            $id = $model->save($insertData); 
            
            $requestUrl = self::$Client . "/currencies/" . $id;
            $ch = curl_init($requestUrl); 
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $response = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);     
            $this->assertEquals(200,$httpcode);               
        }
    }        
    
    /**
     * Fetch a currency by code
     * @dataProvider fetchCurrencyDataProvider
     * @param type $Code
     */
    public function test_fetchCurrency($Code) {
        $requestUrl = self::$Client . "/currencies/" . $Code;          

        $headers = array('Accept: application/json');
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $requestUrl);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);       
        $response = curl_exec($curl);    
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);  
     
        $this->assertEquals(200, $httpCode);
        curl_close($curl);                        
    }
    
    public function googleExchangeDataProvider() {
        return [
            ['SYP', 'CRC', 1]
        ];
    }    

     /**
     * Exchange response test
     * @dataProvider currencyDataProvider
     */
    public function test_exchange($From, $To) {
        $requestUrl = self::$Client . '/currencies/' . $From . '/exchange/' . $To;      
    
        $ch = curl_init($requestUrl); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($ch);       
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);                   
        $response = json_decode($response, TRUE); 
        $this->assertNotNull($response);    
    
        $this->assertArrayHasKey('_links', $response);
        $element = reset($response);      

        $this->assertArrayHasKey('Id',$element);
        $this->assertArrayHasKey('From',$element);
        $this->assertArrayHasKey('To',$element);
        $this->assertArrayHasKey('Status', $element);
        $this->assertArrayHasKey('Value', $element);
        $this->assertArrayHasKey('Timestamp', $element);
        
        $this->assertNotNull($element['From']);
        $this->assertNotNull($element['To']);
        
        $this->assertEquals($From, $element['From']);
        $this->assertEquals($To, $element['To']);
        
        $this->assertNotNull($element['Id']);
        $this->assertNotNull($element['Status']);
        $this->assertNotEmpty($element['Timestamp']);
        $this->assertNotEmpty($element['Value']);             
    }     
    
    /**
     * @dataProvider googleExchangeDataProvider
     * @param type $From
     * @param type $To
     * @param type $Amount
     * @return type
     */
    public function test_googleExchange($From, $To, $Amount) {
        $model = new CurrencyConverter();
        $value = $model->currencyExchange($From, $To, $Amount);          
        
        $this->assertNotEmpty($value);
    }
    public function test()
    {
    }
}