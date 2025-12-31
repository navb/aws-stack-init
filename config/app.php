<?php

return [
    'name' => 'Aws-stack-init',
    'version' => app('git.version'),
    'env' => 'development',
    'providers' => [
        App\Providers\AppServiceProvider::class,
    ],
];
