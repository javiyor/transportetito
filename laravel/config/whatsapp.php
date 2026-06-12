<?php

return [
    /*
    | Meta Cloud API (WhatsApp Business)
    |
    | https://developers.facebook.com/docs/whatsapp/cloud-api
    */
    'api_url' => env('WHATSAPP_API_URL', 'https://graph.facebook.com/v22.0'),
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
    'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
    'from_number' => env('WHATSAPP_FROM_NUMBER'),
];
