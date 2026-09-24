<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'beem' => [
        'enabled' => env('BEEM_SMS_ENABLED', true),
        'api_key' => env('BEEM_SMS_API_KEY'),
        'secret_key' => env('BEEM_SMS_SECRET_KEY'),
        'sender_name' => env('BEEM_SMS_SENDER_NAME', 'IventPro'),
    ],

    'infobip' => [
        'enabled' => env('INFOBIP_ENABLED', true),
        'base_url' => env('INFOBIP_BASE_URL'),
        'api_key' => env('INFOBIP_API_KEY'),
        'sender' => env('INFOBIP_SENDER', 'IventPro'),
        'whatsapp_sender' => env('INFOBIP_WHATSAPP_SENDER'),
        'templates' => [
            'invitation_en' => env('INFOBIP_WHATSAPP_INVITATION_TEMPLATE_EN', 'invitation_tamplate_en'),
            'invitation_sw' => env('INFOBIP_WHATSAPP_INVITATION_TEMPLATE_SW', 'invitation_tamplate_sw'),
            'contribution_en' => env('INFOBIP_WHATSAPP_CONTRIBUTION_TEMPLATE_EN', 'contribution_tamplate_01'),
            'contribution_sw' => env('INFOBIP_WHATSAPP_CONTRIBUTION_TEMPLATE_SW', 'contribution_tamplate_sw'),
            'committee_welcome_en' => env('INFOBIP_WHATSAPP_COMMITTEE_WELCOME_TEMPLATE_EN', 'committee_message_en'),
            'committee_welcome_sw' => env('INFOBIP_WHATSAPP_COMMITTEE_WELCOME_TEMPLATE_SW', 'committee_message_sw'),
            'committee_reminder_en' => env('INFOBIP_WHATSAPP_COMMITTEE_REMINDER_TEMPLATE_EN', 'committee_reminder_message_en'),
            'committee_reminder_sw' => env('INFOBIP_WHATSAPP_COMMITTEE_REMINDER_TEMPLATE_SW', 'committee_reminder_message_sw'),
            'thank_participating' => env('INFOBIP_WHATSAPP_THANK_PARTICIPATING_TEMPLATE', 'thank_participating_tamplate'),
            'thank_contribution' => env('INFOBIP_WHATSAPP_THANK_CONTRIBUTION_TEMPLATE', 'thank_contribution_tamplate'),
        ],
    ],

    'flutterwave' => [
        'client_id' => env('FLUTTERWAVE_CLIENT_ID'),
        'secret_key' => env('FLUTTERWAVE_SECRET_KEY'),
        'encryption_key' => env('FLUTTERWAVE_ENCRYPTION_KEY'),
        'mode' => env('PAYMENTS_MODE', 'test'),
    ],

    'brevo' => [
        'api_key' => env('BREVO_API_KEY'),
        'sender_email' => env('BREVO_SENDER_EMAIL', 'info@iventpro.com'),
        'sender_name' => env('BREVO_SENDER_NAME', 'IventPro'),
    ],

];
