<?php

return [

    /*
    |--------------------------------------------------------------------------
    | POS (Pinpad / Transacciones débito)
    |--------------------------------------------------------------------------
    */

    /**
     * true  = enviarTransaccionPOS devuelve aprobación simulada (no llama al PINPAD).
     * false = envía al dispositivo real.
     * Cámbialo aquí para pruebas.
     */
    'simular_transaccion' => false,

];
