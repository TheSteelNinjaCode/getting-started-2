<?php

use Lib\Prisma\Classes\Prisma;

$prisma = new Prisma();

$prisma->userRole->create([
    'data' => [
        "name" => "Admin"
    ]
]);
