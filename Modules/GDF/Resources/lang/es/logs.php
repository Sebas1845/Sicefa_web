<?php

return [
  'actions' => [
    'submitted'          => 'Enviada',
    'create_all_saved'   => 'Todo guardado',
    'created_from_sigac' => 'Creada desde SIGAC',

    // si tienes más acciones, agrégalas aquí:
    'seen_by_support'    => 'Validada por Apoyo',
    'returned'           => 'Devuelta',
    'sent_treasury'      => 'Enviada a Tesorería',
  ],

  'fields' => [
    'total_amount' => 'Total',
    'transport'    => 'Transporte',
    'direction'    => 'Trayecto',
    'trip_type'    => 'Tipo de viaje',
    'unit_amount'  => 'Valor unitario',
  ],

  'transport' => [
    'motorcycle' => 'Moto',
    'moto'       => 'Moto',
    'bus'        => 'Bus',
    'van'        => 'Camioneta',

    // por si guardas estos también
    'terrestre'  => 'Bus',
    'camioneta'  => 'Camioneta',
    'aereo'      => 'Aéreo',
  ],

  'direction' => [
    'one_way'    => 'Solo ida',
    'round_trip' => 'Ida y vuelta',
  ],

  'trip_type' => [
    'one_way'    => 'Solo ida',
    'round_trip' => 'Ida y vuelta',
  ],
];
