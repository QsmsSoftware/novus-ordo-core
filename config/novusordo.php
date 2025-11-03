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

    /*
    |--------------------------------------------------------------------------
    | Show the ready for next turn button.
    |--------------------------------------------------------------------------
    |
    | Setting this to true will show the "Ready for next turn" button in the
    | Dashboard.
    |
    */

    'show_ready_for_next_turn_button' => env('NOVUSORDO_SHOW_READY_FOR_NEXT_TURN_BUTTON', true),
];
