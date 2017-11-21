<?php

return [

    //-------------------------------
    // Timezone for insert dates in database
    // If you want Gateway not set timezone, just leave it empty
    //--------------------------------
    'timezone' => 'Asia/Tehran',

    //--------------------------------
    // Zarinpal gateway
    //--------------------------------
    'zarinpal' => [
        'title'        => 'درگاه پرداخت زرین پال',
        'merchant-id'  => 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',
        'type'         => 'zarin-gate',             // Types: [zarin-gate || normal]
        'callback-url' => '/',
        'server'       => 'germany',                // Servers: [germany || iran || test]
        'email'        => 'email@gmail.com',
        'mobile'       => '09xxxxxxxxx',
        'description'  => 'description',
    ],

    //--------------------------------
    // Mellat gateway
    //--------------------------------
    'mellat' => [
        'title'        => 'درگاه پرداخت بانک ملت',
        'username'     => '',
        'password'     => '',
        'terminalId'   => 0000000,
        'callback-url' => '/'
    ],

    //--------------------------------
    // Saman gateway
    //--------------------------------
    'saman' => [
        'title'        => 'درگاه پرداخت بانک سامان',
        'merchant'     => '',
        'password'     => '',
        'callback-url' => '/',
    ],

    //--------------------------------
    // Payline gateway
    //--------------------------------
    'payline' => [
        'title'        => 'درگاه پرداخت Payline',
        'api'          => 'xxxxx-xxxxx-xxxxx-xxxxx-xxxxxxxxxxxxxxxxxxxxxxxxxxxx',
        'callback-url' => '/'
    ],

    //--------------------------------
    // Sadad gateway
    //--------------------------------
    'sadad' => [
        'title'         => 'درگاه پرداخت بانک ملی',
        'merchant'      => '',
        'transactionKey'=> '',
        'terminalId'    => 000000000,
        'callback-url'  => '/'
    ],

    //--------------------------------
    // JahanPay gateway
    //--------------------------------
    'jahanpay' => [
        'title'        => 'درگاه پرداخت JahanPay',
        'api'          => 'xxxxx-xxxxx-xxxxx-xxxxx-xxxxxxxxxxxxxxxxxxxxxxxxxxxx',
        'callback-url' => '/'
    ],

    //--------------------------------
    // Parsian gateway
    //--------------------------------
    'parsian' => [
        'title'        => 'درگاه پرداخت پارسیان',
        'pin'          => 'xxxxxxxxxxxxxxxxxxxx',
        'callback-url' => '/'
    ],
    //--------------------------------
    // Pasargad gateway
    //--------------------------------
    'pasargad' => [
        'title'         => 'درگاه پرداخت بانک پاسارگاد',
        'terminalId'    => 000000,
        'merchantId'    => 000000,
        'certificate-path'    => storage_path('gateway/pasargad/certificate.xml'),
        'callback-url' => '/gateway/callback/pasargad'
    ],
    //--------------------------------
    // Irankish gateway
    //--------------------------------
    'irankish' => [
        'title'         => 'درگاه پرداخت ایران کیش',
        'merchantId'    => 000000,
        'sha1key'    => 000000,
        'callback-url' => '/gateway/callback/irankish'
    ],
    //-------------------------------
    // Tables names
    //--------------------------------
    'table'=> 'gateway_transactions',
];
