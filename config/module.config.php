<?php
return [
    'service_manager' => [
        'factories' => [
            \MicroIceExchangeRate\V1\Rest\Currencies\CurrenciesResource::class => \MicroIceExchangeRate\V1\Rest\Currencies\CurrenciesResourceFactory::class,
            \MicroIceExchangeRate\V1\Rest\Currencies\CurrenciesModel::class => \MicroIceExchangeRate\V1\Rest\Currencies\CurrenciesModelFactory::class,
            \MicroIceExchangeRate\V1\Rest\Exchange\ExchangeResource::class => \MicroIceExchangeRate\V1\Rest\Exchange\ExchangeResourceFactory::class,
            \MicroIceExchangeRate\V1\Rest\Exchange\ExchangeModel::class => \MicroIceExchangeRate\V1\Rest\Exchange\ExchangeModelFactory::class,
        ],
    ],
    'router' => [
        'routes' => [
            'micro-ice-currency-exchange.rest.currencies' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/currencies[/:currencies_id]',
                    'defaults' => [
                        'controller' => 'MicroIceExchangeRate\\V1\\Rest\\Currencies\\Controller',
                    ],
                ],
            ],
            'micro-ice-currency-exchange.rest.exchange' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/currencies/:currency_id_1/exchange[/:currency_id_2]',
                    'defaults' => [
                        'controller' => 'MicroIceExchangeRate\\V1\\Rest\\Exchange\\Controller',
                    ],
                ],
            ],
        ],
    ],
    'zf-versioning' => [
        'uri' => [
            0 => 'micro-ice-currency-exchange.rest.currencies',
            1 => 'micro-ice-currency-exchange.rest.exchange',
        ],
    ],
    'zf-rest' => [
        'MicroIceExchangeRate\\V1\\Rest\\Currencies\\Controller' => [
            'listener' => \MicroIceExchangeRate\V1\Rest\Currencies\CurrenciesResource::class,
            'route_name' => 'micro-ice-currency-exchange.rest.currencies',
            'route_identifier_name' => 'currencies_id',
            'collection_name' => 'currencies',
            'entity_http_methods' => [
                0 => 'GET',
                1 => 'PATCH',
            ],
            'collection_http_methods' => [
                0 => 'GET',
            ],
            'collection_query_whitelist' => [
                0 => 'filter',
                1 => 'sort',
            ],
            'page_size' => 999,
            'page_size_param' => null,
            'entity_class' => \MicroIceExchangeRate\V1\Rest\Currencies\CurrenciesEntity::class,
            'collection_class' => \MicroIceExchangeRate\V1\Rest\Currencies\CurrenciesCollection::class,
            'service_name' => 'Currencies',
        ],
        'MicroIceExchangeRate\\V1\\Rest\\Exchange\\Controller' => [
            'listener' => \MicroIceExchangeRate\V1\Rest\Exchange\ExchangeResource::class,
            'route_name' => 'micro-ice-currency-exchange.rest.exchange',
            'route_identifier_name' => 'exchange_id',
            'collection_name' => 'exchange',
            'entity_http_methods' => [],
            'collection_http_methods' => [
                0 => 'GET',
                1 => 'POST',
                2 => 'DELETE',
            ],
            'collection_query_whitelist' => [
                0 => 'filter',
                1 => 'sort',
            ],
            'page_size' => 999,
            'page_size_param' => null,
            'entity_class' => \MicroIceExchangeRate\V1\Rest\Exchange\ExchangeEntity::class,
            'collection_class' => \MicroIceExchangeRate\V1\Rest\Exchange\ExchangeCollection::class,
            'service_name' => 'Exchange',
        ],
    ],
    'zf-content-negotiation' => [
        'controllers' => [
            'MicroIceExchangeRate\\V1\\Rest\\Currencies\\Controller' => 'HalJson',
            'MicroIceExchangeRate\\V1\\Rest\\Exchange\\Controller' => 'HalJson',
        ],
        'accept_whitelist' => [
            'MicroIceExchangeRate\\V1\\Rest\\Currencies\\Controller' => [
                0 => 'application/vnd.micro-ice-currency-exchange.v1+json',
                1 => 'application/hal+json',
                2 => 'application/json',
            ],
            'MicroIceExchangeRate\\V1\\Rest\\Exchange\\Controller' => [
                0 => 'application/vnd.micro-ice-currency-exchange.v1+json',
                1 => 'application/hal+json',
                2 => 'application/json',
            ],
        ],
        'content_type_whitelist' => [
            'MicroIceExchangeRate\\V1\\Rest\\Currencies\\Controller' => [
                0 => 'application/vnd.micro-ice-currency-exchange.v1+json',
                1 => 'application/json',
                2 => 'application/x-www-form-urlencoded',
            ],
            'MicroIceExchangeRate\\V1\\Rest\\Exchange\\Controller' => [
                0 => 'application/vnd.micro-ice-currency-exchange.v1+json',
                1 => 'application/json',
                2 => 'application/x-www-form-urlencoded',
            ],
        ],
    ],
    'zf-hal' => [
        'metadata_map' => [
            \MicroIceExchangeRate\V1\Rest\Currencies\CurrenciesEntity::class => [
                'entity_identifier_name' => 'Id',
                'route_name' => 'micro-ice-currency-exchange.rest.currencies',
                'route_identifier_name' => 'currencies_id',
                'hydrator' => \Zend\Hydrator\ArraySerializable::class,
            ],
            \MicroIceExchangeRate\V1\Rest\Currencies\CurrenciesCollection::class => [
                'entity_identifier_name' => 'Id',
                'route_name' => 'micro-ice-currency-exchange.rest.currencies',
                'route_identifier_name' => 'currencies_id',
                'is_collection' => true,
            ],
            \MicroIceExchangeRate\V1\Rest\Exchange\ExchangeEntity::class => [
                'entity_identifier_name' => 'Id',
                'route_name' => 'micro-ice-currency-exchange.rest.exchange',
                'route_identifier_name' => 'exchange_id',
                'hydrator' => \Zend\Hydrator\ArraySerializable::class,
            ],
            \MicroIceExchangeRate\V1\Rest\Exchange\ExchangeCollection::class => [
                'entity_identifier_name' => 'Id',
                'route_name' => 'micro-ice-currency-exchange.rest.exchange',
                'route_identifier_name' => 'exchange_id',
                'is_collection' => true,
            ],
        ],
    ],
];
