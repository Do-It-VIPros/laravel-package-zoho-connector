<?php

return [
    'driver' => 'daily',  // Peut être 'single' ou 'stack'
    'path' => storage_path('logs/zohoconnector/zohoconnector_log.log'),
    'level' => 'info',  // Niveau de log : 'info', 'warning', 'error', etc.
    'days' => 14,        // Rotation des logs sur 14 jours
];
