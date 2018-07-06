<?php

return [
    'base_class_name' => \Illuminate\Database\Eloquent\Model::class,
    'no_timestamps'   => null,
    'date_format'     => null,
    'connection'      => null,
    'namespace' => 'App\Models',
    'output_path' => 'Models',
    'except-tables' => 'migrations,users,password_resets',
    'controller_path' => '',
    'routes_path' => 'routes/web.php',
    'request_namespace' => 'App\Http\Requests',
    'request_path' => 'Http/Requests',
    'api_routes_path' => 'routes/api.php'
];