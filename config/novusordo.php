<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Always render Javascript
    |--------------------------------------------------------------------------
    |
    | Setting this to true will force the application to always rerender
    | permanent fixed Javascript resources before linking them they're accessed.
    | This is meant for development, where some changes in the code might not
    | be committed yet and would go unnoticed by caching.
    |
    */

    'always_rerender_permanent_js' => env('NOVUSORDO_ALWAYS_RERENDER_PERMANENT_JS', false),

];
