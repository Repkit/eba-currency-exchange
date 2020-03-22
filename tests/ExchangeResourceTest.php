<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace MicroIceExchangeRate;


use PHPUnit_Framework_TestCase as TestCase;
use ApplicationTest\Bootstrap;
use MicroIceExchangeRate\V1\Rest\Exchange\Mapper\Db\Model\ExchangeMapper;
use MicroIceExchangeRate\V1\Rest\Exchange\ExchangeResource;
use MicroIceExchangeRate\V1\Rest\Exchange\ExchangeCollection;
use Zend\Paginator\Adapter\AdapterInterface;
use Zend\Stdlib\ArrayObject;
use Zend\Mvc\Router\RouteMatch;
use Zend\Db\Sql\Select;
use ZF\ContentNegotiation\Request;
use ZF\ApiProblem\ApiProblem;
use ZF\Rest\ResourceEvent;

class ExchangeResourceTest extends TestCase
{
    private static $Client = APP_URL;
    private $serviceManager;           
    private $DbMapper;           
    private $CurrencyConverter;           
    private $Request;           
    
   /**
    * Set up
    */
    public function setUp()
    {
        $this->serviceManager = Bootstrap::getServiceManager();

        $this->CurrencyConverter = $this->getMockBuilder(CurrencyConverter::class)
                                        ->setMethods(['currencyExchange'])
                                        ->getMock();

        $this->DbMapper = $this ->getMockBuilder(ExchangeMapper::class)
                                ->disableOriginalConstructor()
                                ->setMethods(['save','fetchExchangeRow','fetchLatestExchange','sendMailAlert','sendMailAlert_PhpMailer','filter','updateStatus','isValidParameter'])
                                ->getMock();
                            
        $this->DbMapper ->method('save')
                        ->will($this->returnValue(3));

        $this->Request = $this->getMockBuilder(Request::class)
                        ->setMethods(['getQuery'])
                        ->getMock();
    }
    
    public function ValidParameterMapProvider()
    {
        return [
            [
                [
                    ['USD', true],
                    ['EUR', true],
                    ['LEU', true],
                    ['1.1', true],
                    ['/as', false],
                    ['US', false],
                    ['(()', false],
                ],
            ]
        ];
    } 

    public function ExchangeRowProvider()
    {
        return [
            [
                [
                    0 => [
                        "Id" => "3",
                        "From" => "RON",
                        "To" => "EUR",
                        "Value" => "0.2247",
                        "Date" => "2016-09-12",
                        "Status" => "0",
                        "Timestamp" => "2016-09-12 17:09:50"
                        ]
                ],
            ]
        ];
    }

    public function FetchAllProvider()
    {
        return [
            [
                [ 
                    'filter' => [
                        $this->equalTo(null),
                        $this->equalTo(['From' => 'USD']),
                    ],
                    'route' => [
                        'currency_code_from' => 'USD',
                    ]                    
                ],
                [ 
                    'filter' => [
                        $this->equalTo(['Timestamp' => 'Desc']),
                        $this->equalTo(null),
                    ],
                    'route' => [
                        'currency_code_from' => 'all',
                    ]                    
                ],
            ],
        ];
    } 

    public function FetchAllErrorProvider()
    {
        return [
            [
                [ 
                    'route' => [
                        'currency_code_from' => 'US',
                    ],
                    'status' => 405                    
                ],
                [ 
                    'route' => [
                        'currency_code_from' => '(()',
                    ], 
                    'status' => 405                    
                ],
            ],
        ];
    }

    /**
     *   @dataProvider ValidParameterMapProvider
     */
    public function testCreate_newExchangeInDB($ValidParameterMap)
    {
        $routeMatch = new RouteMatch(array(
                'currency_code_from' => 'EUR', 
                'currency_code_to' => 'USD',
            )
        );
        
        $resourceEvent = new ResourceEvent('create', null, [
            'data' =>   new ArrayObject ([
                            'Value' => '1.1',
                        ], ArrayObject::ARRAY_AS_PROPS)
        ]); 
        
        $resource = new ExchangeResource($this->DbMapper, $this->CurrencyConverter);
        $resourceEvent->setRouteMatch($routeMatch);
        
        $this->DbMapper ->expects($this->exactly(2))
                        ->method('isValidParameter')
                        ->will($this->returnValueMap($ValidParameterMap));


        $id = $resource->dispatch($resourceEvent);
        $this->assertTrue(is_numeric($id));
    }

    public function testCreate_InvalidCurrencies()
    {
        $this->setExpectedException("\Exception");
        
        $resource = new ExchangeResource($this->DbMapper, $this->CurrencyConverter);
        
        $resourceEvent = new ResourceEvent('create', null, [
            'data' =>   new ArrayObject ([
                            'Value' => '1.1',
                        ], ArrayObject::ARRAY_AS_PROPS)
        ]); 

        $id = $resource->dispatch($resourceEvent);
    }

    /**
     *   @dataProvider ValidParameterMapProvider
     */
    public function testCreate_InvalidValue($ValidParameterMap)
    {
        $this->setExpectedException("\Exception");
        
        $routeMatch = new RouteMatch(array(
                'currency_code_from' => 'EUR', 
                'currency_code_to' => 'USD',
            )
        );
        
        $resourceEvent = new ResourceEvent('create', null, [
            'data' =>   new ArrayObject ([
                            'Value' => '11a',
                        ], ArrayObject::ARRAY_AS_PROPS)
        ]); 
        
        $resource = new ExchangeResource($this->DbMapper, $this->CurrencyConverter);
        $resourceEvent->setRouteMatch($routeMatch);
        
        $this->DbMapper ->expects($this->exactly(2))
                        ->method('isValidParameter')
                        ->will($this->returnValueMap($ValidParameterMap));


        $id = $resource->dispatch($resourceEvent);
    }

    public function testFetch_ExchangeBetweenCurrencyWithSave()
    {
        $this->CurrencyConverter->method('currencyExchange')
                                ->willReturn(1.1)
                                ->with(
                                    $this->equalTo('EUR'),
                                    $this->equalTo('USD'),
                                    $this->equalTo(1)
                                );
        
        $this->Request  ->expects($this->once())
                        ->method('getQuery')
                        ->willReturn(array('amount' => null));

        $this->DbMapper ->expects($this->once())
                        ->method('save');

        $routeMatch = new RouteMatch(array(
                'currency_code_from' => 'EUR',
            )
        );
        
        $resourceEvent = new ResourceEvent('fetch', null, [
            'id' => 'USD',
        ]); 


        $resource = new ExchangeResource($this->DbMapper, $this->CurrencyConverter);
        $resourceEvent->setRouteMatch($routeMatch);
        $resourceEvent->setRequest($this->Request);
        $result = $resource->dispatch($resourceEvent);

        if (isset($result[0]))
        {
            $result = $result[0];
        }

        $this->assertEquals('EUR', $result['From']);
        $this->assertEquals('USD', $result['To']);
        $this->assertEquals('1.1', $result['Value']);
    }
    
    /**
     *   @dataProvider ExchangeRowProvider
     */
    public function testFetch_ExchangeBetweenCurrencyFromDatabase($Row)
    {        
        $this->Request  ->expects($this->once())
                        ->method('getQuery')
                        ->willReturn(array('amount' => null));

        $this->DbMapper ->expects($this->never())
                        ->method('save');

        $this->DbMapper ->expects($this->once())
                        ->method('fetchExchangeRow')
                        ->with(
                                $this->equalTo('EUR'),
                                $this->equalTo('USD'),
                                $this->callback(
                                    function ($date) 
                                    {
                                        return preg_match('/[1-9][0-9]{3}[-[0-9]{2}]{2}/', $date) !== false;
                                    }
                                )
                            )
                        ->willReturn($Row);

        $routeMatch = new RouteMatch(array(
                'currency_code_from' => 'EUR',
            )
        );
        
        $resourceEvent = new ResourceEvent('fetch', null, [
            'id' => 'USD',
        ]); 

        $resource = new ExchangeResource($this->DbMapper, $this->CurrencyConverter);
        $resourceEvent->setRouteMatch($routeMatch);
        $resourceEvent->setRequest($this->Request);
        $result = $resource->dispatch($resourceEvent);

        $this->assertEquals($result, $Row);
    }    

    /**
     *   @dataProvider ExchangeRowProvider
     */
    public function testFetch_ExchangeBetweenCurrencyFromGoogleError($Row)
    {
        $this->CurrencyConverter->expects($this->once())
                                ->method('currencyExchange')
                                ->will($this->throwException(new \Exception));
        
        $this->Request  ->expects($this->once())
                        ->method('getQuery')
                        ->willReturn(array('amount' => null));

        $this->DbMapper ->expects($this->never())
                        ->method('save');

        $this->DbMapper ->expects($this->once())
                        ->method('fetchLatestExchange')
                        ->with(
                                $this->equalTo('EUR'),
                                $this->equalTo('USD')
                            )
                        ->willReturn($Row);

        $routeMatch = new RouteMatch(array(
                'currency_code_from' => 'EUR',
            )
        );
        
        $resourceEvent = new ResourceEvent('fetch', null, [
            'id' => 'USD',
        ]); 

        $resource = new ExchangeResource($this->DbMapper, $this->CurrencyConverter);
        $resourceEvent->setRouteMatch($routeMatch);
        $resourceEvent->setRequest($this->Request);
        $result = $resource->dispatch($resourceEvent);

        $this->assertEquals($result, $Row);
    }

    /**
     *   @dataProvider FetchAllProvider
     */
    public function testFetchAll_SingleCurrency($Data)
    {
        $adapter = $this->getMockBuilder(AdapterInterface::class)
                        ->getMock();

        $this->Request  ->expects($this->once())
                        ->method('getQuery')
                        ->willReturn(array('sort' => null, 'filter' => null));

        $map = $this->ValidParameterMapProvider();
        $this->DbMapper ->expects($this->exactly(1))
                        ->method('isValidParameter')
                        ->will($this->returnValueMap($map[0][0]));

        $this->DbMapper ->expects($this->exactly(1))
                        ->method('filter')
                        ->with(
                                $Data['filter'][0],
                                $Data['filter'][1]
                            )
                        ->willReturn($adapter);
        
        $resource = new ExchangeResource($this->DbMapper, $this->CurrencyConverter);
        
        $routeMatch = new RouteMatch(
            $Data['route']
        );
        
        $resourceEvent = new ResourceEvent('fetchAll', null, [
        ]); 

        $resourceEvent->setRouteMatch($routeMatch);
        $resourceEvent->setRequest($this->Request);

        $result = $resource->dispatch($resourceEvent);

        $this->assertTrue($result instanceof ExchangeCollection);
        $this->assertEquals($result->getAdapter(), $adapter);
    }

    /**
     *   @dataProvider FetchAllErrorProvider
     */
    public function testFetchAll_Exception($Data)
    {
        $adapter = $this->getMockBuilder(AdapterInterface::class)
                        ->getMock();

        $map = $this->ValidParameterMapProvider();
        $this->DbMapper ->expects($this->exactly(1))
                        ->method('isValidParameter')
                        ->will($this->returnValueMap($map[0][0]));

        
        $resource = new ExchangeResource($this->DbMapper, $this->CurrencyConverter);
        
        $routeMatch = new RouteMatch(
            $Data['route']
        );
        
        $resourceEvent = new ResourceEvent('fetchAll', null, [
        ]); 

        $resourceEvent->setRouteMatch($routeMatch);
        $resourceEvent->setRequest($this->Request);

        $result = $resource->dispatch($resourceEvent);
        $this->assertTrue($result instanceof ApiProblem);

        $result = $result->toArray();
        $this->assertEquals($Data['status'], $result['status']);
    }
}