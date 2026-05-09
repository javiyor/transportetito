<?php

return [
    // Roles are the only authorization primitive for the MVP.
    // Keep this list small and stable; validate all admin inputs against it.
    'available' => [
        'admin',
        'operaciones',
        'facturacion',
        'cobranzas',
        'lectura',
    ],
];
