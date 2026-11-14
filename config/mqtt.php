<?php

return [
    'host' => env('MQTT_HOST', '160.187.144.147'),
    'port' => (int) env('MQTT_PORT', 1883),
    'client_id' => env('MQTT_CLIENT_ID', 'ekg-laravel-subscriber'),
    'username' => env('MQTT_USERNAME'),
    'password' => env('MQTT_PASSWORD'),
    'keepalive' => (int) env('MQTT_KEEPALIVE', 60),
    'connect_timeout' => (int) env('MQTT_CONNECT_TIMEOUT', 10),
    'reconnect_attempts' => (int) env('MQTT_RECONNECT_ATTEMPTS', 0),
    'reconnect_delay' => (int) env('MQTT_RECONNECT_DELAY', 3),

    'legacy_device_uid' => env('MQTT_DEVICE_UID'),
    'legacy_facility_id' => env('MQTT_FASKES_ID'),
    'legacy_facility_code' => env('MQTT_FASKES_CODE'),
];
