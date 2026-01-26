<?php

return [
    'name' => 'GDF',

    // IDs de la tabla AREAS (NO budget_items)
    'area_groups' => [
        'academic'  => [2],
        'campesena' => [1],
    ],

    // IDs de la tabla BUDGET_ITEMS (según tu captura)
    'budget_item_groups' => [
        // Ajusta según tus IDs reales:
        'academic'  => [4, 3, 1], // p.ej. FORMACIÓN REGULAR (4), REGULAR POPULAR (3) y FULL REGULAR (1)
        'campesena' => [2,], // p.ej. CAMPESENA (2) 
    ],


    'requests_owner_column' => 'person_id', // o created_by

    // Para rubros asignados en user_area_budget_item:
    // 'user' => uabi.user_id
    // 'person' => uabi.person_id
    'budget_owner_mode' => 'user',

    'country_id' => 25,
    'default_department_id' => 0,
];
