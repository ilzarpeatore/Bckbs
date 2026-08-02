<?php

return [
    'CURRENCY' => [
        'CODE' => '',        
        'POSITION' => ''
    ],

    'ONESIGNAL' => [
        'APP_ID' => env('ONESIGNAL_APP_ID'),
        'REST_API_KEY' => env('ONESIGNAL_REST_API_KEY'),
    ],

    'ADMOB' => [
        'BannerId' => '',
        'InterstitialId' => '',
        'BannerIdIos' => '',
        'InterstitialIdIos' => '',
        'NativeAdId'  => '',
        'NativeAdIdIos' => '',
    ],

    'AdsBannerDetail' => [
        'Show_Ads_On_Diet_Detail' => '',
        'Show_Banner_Ads_OnDiet' => '',
        
        'Show_Ads_On_Workout_Detail' => '',
        'Show_Banner_On_Workouts' => '',

        'Show_Ads_On_Exercise_Detail' => '',
        'Show_Banner_On_Equipment' => '',

        'Show_Ads_On_Product_Detail' => '',
        'Show_Banner_On_Product' => '',
       
        'Show_Ads_On_Progress_Detail' => '',
        'Show_Banner_On_BodyPart' => '',
        
        'Show_Ads_On_Blog_Detail' => '',
        'Show_Banner_On_Level' => '',

        'Show_Ads_On_Chatbot' => '',
        'Show_Ads_On_Game' => '',
        'Show_Ads_On_Slider_Banner' => '',
        'Show_Ads_On_List_View' => '',
    ],
    
    'CHATGPT' => [
        'API_KEY' => '',
    ],

    'QUOTE' => [
        'TIME' => '',
    ],

    'APPVERSION' => [
        'ANDROID_FORCE_UPDATE' => '',
        'ANDROID_VERSION_CODE' => '',
        'PLAYSTORE_URL' => '',
        'IOS_FORCE_UPDATE' => '',
        'IOS_VERSION' => '',
        'APPSTORE_URL' => '',
    ],
    'CRISP_CHAT_CONFIGURATION' => [
        'WEBSITE_ID' => '',
        'ENABLE/DISABLE' => '',
    ],
    'MOBILE_GAME_ENABLE' => [
        'TYPE' => '',
    ],
];