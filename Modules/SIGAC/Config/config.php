<?php

return [

    'name' => 'SIGAC',

    /*
    |--------------------------------------------------------------------------
    | Correos de Seguridad / Portería
    |--------------------------------------------------------------------------
    | A estos correos se les enviará el recordatorio con el PDF de autorización
    | horas antes de la visita.
    */
    'security_emails' => [
        'camilosanches67255@gmail.com',
        // agrega los que necesites...
    ],

    'security_reminder_hours' => 12,

    'pdf_logo_path'              => 'images/sigac/pdf/logo_sena.png', // dentro de public/
    'institution_name'           => 'SERVICIO NACIONAL DE APRENDIZAJE – SENA',
    'center_name'                => 'Centro de Formación Agroindustrial',
    'city_name'                  => 'Neiva',
    'coordination_name'          => 'NOMBRE COMPLETO COORDINADOR(A)',
    'coordination_signature_path'=> 'images/sigac/pdf/firma.png', // dentro de public/
    'center_address'             => 'Dirección del Centro de Formación',
    'center_contact'             => 'Teléfono 3182027464 – correo@misena.edu.co',
];

