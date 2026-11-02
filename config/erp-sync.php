<?php

return [
    'enabled' => env('ERP_SYNC_ENABLED', false),
    'scheduled' => env('ERP_SYNC_SCHEDULED', false),
    'connection' => env('ERP_DB_CONNECTION', 'erp'),
    'sync_passwords' => env('ERP_SYNC_PASSWORDS', false),
];
