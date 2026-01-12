<?php

return [
    'ldap' => [
        'api_url' => env('LDAP_API_URL','http://10.25.2.25:8080/ldap'),
        'enpoint' => '/ldap',
        'timeout' => 10,
        'retry' => 3
    ],
    'otp' => [
        'api_url' => env('OTP_API_URL','http://10.25.3.81:5002/api'),
        'endpoint' => [
            'generate' => '/generate',
            'check' => '/check'
        ],
        'config' => [
            'origin' => env('OTP_ORIGIN','Ubora'),
            'otpOveroutLine' => 300000,
            'customMessage' => "Votre code de vérification Ubora est {{otpCode}}, ce code expire dans 5 minutes.",
            'senderName' => env('OTP_SENDER_NAME','Ubora'),
            'ignoreOrangeNumbers' => false,
        ],
        'timeout' => 10,
        'retry' => 2
    ],

];
