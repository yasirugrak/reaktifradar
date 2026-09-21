<?php

return [
    'email' => env('CONTACT_EMAIL', env('MAIL_FROM_ADDRESS')),
    'notification_email' => env('CALLBACK_NOTIFICATION_EMAIL'),
    'phone' => env('CONTACT_PHONE'),
];
