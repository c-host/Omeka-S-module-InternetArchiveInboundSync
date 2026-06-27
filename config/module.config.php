<?php declare(strict_types=1);

namespace InternetArchiveInboundSync;

use InternetArchiveInboundSync\Service;

return [
    'service_manager' => [
        'factories' => [
            Service\EmbedMediaThumbnailService::class => Service\EmbedMediaThumbnailServiceFactory::class,
            Service\ModuleSettings::class => Service\ModuleSettingsFactory::class,
            Service\IaHttpClient::class => Service\IaHttpClientFactory::class,
            Service\IaMetadataClient::class => Service\IaMetadataClientFactory::class,
            Service\IaCollectionClient::class => Service\IaCollectionClientFactory::class,
            Service\IaIiifProbe::class => Service\IaIiifProbeFactory::class,
            Service\BilingualTextSplitter::class => Service\BilingualTextSplitterFactory::class,
            Service\Iso6392LanguageCatalog::class => Service\Iso6392LanguageCatalogFactory::class,
            Service\IaLanguageResolver::class => Service\IaLanguageResolverFactory::class,
            Service\LabelCatalog::class => Service\LabelCatalogFactory::class,
            Service\PayloadBuilder::class => Service\PayloadBuilderFactory::class,
            Service\ItemSyncService::class => Service\ItemSyncServiceFactory::class,
            Service\IdentifierResolverService::class => Service\IdentifierResolverServiceFactory::class,
            Service\SyncRunService::class => Service\SyncRunServiceFactory::class,
            Service\SyncPresetService::class => Service\SyncPresetServiceFactory::class,
            Service\SetupStatusService::class => Service\SetupStatusServiceFactory::class,
        ],
        'invokables' => [
            Service\IaIdentifierParser::class => Service\IaIdentifierParser::class,
        ],
    ],
    'controllers' => [
        'invokables' => [
            'InternetArchiveInboundSync\Controller\Admin\Import' => Controller\Admin\ImportController::class,
            'InternetArchiveInboundSync\Controller\Admin\History' => Controller\Admin\HistoryController::class,
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\ConfigForm::class => Form\ConfigForm::class,
            Form\ImportForm::class => Form\ImportForm::class,
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
    'router' => [
        'routes' => [
            'admin' => [
                'child_routes' => [
                    'internet-archive-inbound-sync' => [
                        'type' => \Laminas\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/internet-archive-inbound-sync',
                            'defaults' => [
                                '__NAMESPACE__' => 'InternetArchiveInboundSync\Controller\Admin',
                                '__ADMIN__' => true,
                                'controller' => 'Import',
                                'action' => 'index',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'default' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/:controller[/:action[/:id]]',
                                    'constraints' => [
                                        'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                        'id' => '\d+',
                                    ],
                                    'defaults' => [
                                        'controller' => 'Import',
                                        'action' => 'index',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'navigation' => [
        'AdminModule' => [
            'internet-archive-inbound-sync' => [
                'label' => 'IA Inbound', // @translate
                'route' => 'admin/internet-archive-inbound-sync/default',
                'controller' => 'import',
                'action' => 'index',
                'resource' => 'InternetArchiveInboundSync\Controller\Admin\Import',
                'class' => 'o-icon- fa-cloud-download-alt',
                'pages' => [
                    [
                        'label' => 'Import', // @translate
                        'route' => 'admin/internet-archive-inbound-sync/default',
                        'controller' => 'import',
                        'action' => 'index',
                        'resource' => 'InternetArchiveInboundSync\Controller\Admin\Import',
                    ],
                    [
                        'label' => 'History', // @translate
                        'route' => 'admin/internet-archive-inbound-sync/default',
                        'controller' => 'history',
                        'action' => 'browse',
                        'resource' => 'InternetArchiveInboundSync\Controller\Admin\History',
                    ],
                ],
            ],
        ],
    ],
];
