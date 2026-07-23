<?php

return [
    'password' => env('DEMO_PASSWORD', 'password'),

    'roles' => [
        'admin' => json_decode(env('USER_ADMIN', '[]'), true) ?? [],
        'manager' => json_decode(env('USER_MANAGER', '[]'), true) ?? [],
        'coach' => json_decode(env('USER_COACH', '[]'), true) ?? [],
        'collaborator' => json_decode(env('USER_COLLABORATOR', '[]'), true) ?? [],
    ],
];
