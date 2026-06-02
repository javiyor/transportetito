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
    // Base directory inside the Laravel app for certificates.
    // Expected layout:
    // certificados/<cuit_digits>/<env>/cert.pem
    // certificados/<cuit_digits>/<env>/key.pem
    'certificates_base_dir' => base_path('certificados'),
];
