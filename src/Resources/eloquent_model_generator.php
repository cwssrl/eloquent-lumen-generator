<?php
/**
 * Created by PhpStorm.
 * User: samuele.salvatico
 * Date: 26/06/2018
 * Time: 17:00
 */
return [
    'model_defaults' => [
        'namespace' => 'App\Models',
        'output_path' => 'Models',
        'except-tables' => 'migrations,users,password_resets',
        'controller_path' => '',
        'routes_path' => 'routes/web.php',
        'request_namespace' => 'App\Http\Requests',
        'request_path' => 'Http/Requests',
    ],
    'base_class_name' => 'App\Models\BaseModel',
    'no_timestamps' => null,
    'date_format' => null,
    'connection' => null,
    'namespace' => 'App\Models',
    'output_path' => 'Models',
    'except-tables' => 'migrations,users,password_resets',
    'controller_path' => '',
    'routes_path' => 'routes/web.php',
    'request_namespace' => 'App\Http\Requests',
    'request_path' => 'Http/Requests',
    'api_routes_path' => 'routes/api.php'
];
