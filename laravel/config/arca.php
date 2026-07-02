<?php

return [
    'wsaa' => [
        'homologacion' => [
            'wsdl' => 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms?WSDL',
        ],
        'produccion' => [
            'wsdl' => 'https://wsaa.afip.gov.ar/ws/services/LoginCms?WSDL',
        ],
    ],
    'wsfe' => [
        'homologacion' => [
            'wsdl' => 'https://wswhomo.afip.gov.ar/wsfev1/service.asmx?WSDL',
        ],
        'produccion' => [
            'wsdl' => 'https://servicios1.afip.gov.ar/wsfev1/service.asmx?WSDL',
        ],
    ],
    'ws_sr_padron_a5' => [
        'homologacion' => [
            'wsdl' => 'https://awshomo.afip.gov.ar/sr-padron/webservices/personaServiceA5?WSDL',
        ],
        'produccion' => [
            'wsdl' => 'https://aws.afip.gov.ar/sr-padron/webservices/personaServiceA5?WSDL',
        ],
    ],
    // Base directory inside the Laravel app for certificates.
    // Expected layout:
    // certificados/<cuit_digits>/<env>/cert.pem
    // certificados/<cuit_digits>/<env>/key.pem
    'certificates_base_dir' => base_path('certificados'),
];
