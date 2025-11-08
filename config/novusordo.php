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

    /*
    |--------------------------------------------------------------------------
    | The timezone to use when setting turn expiration time
    | at the end of the day.
    |--------------------------------------------------------------------------
    */

    'timezone_for_turn_expiration' => env('NOVUSORDO_TIMEZONE_FOR_TURN_EXPIRATION', date_default_timezone_get()),

    /*
    |--------------------------------------------------------------------------
    | Minimum delay in minutes before the expiration time of the turn.
    |--------------------------------------------------------------------------
    |
    | If the end of the day is less than that, the expiration time will be set
    | to the end of the next day. This should be set to a value smaller than
    | the time limit for turn, see also:
    | NOVUSORDO_TURN_TIME_LIMIT_MINUTES
    |
    */

    'minimum_delay_before_turn_expiration_minutes' => env('NOVUSORDO_MINIMUM_DELAY_BEFORE_TURN_EXPIRATION_MINUTES', 0),

    /*
    |--------------------------------------------------------------------------
    | Time limit in minutes for turns.
    |--------------------------------------------------------------------------
    |
    | If 0, turns won't have a time limit. The expiration time is calculated
    | the start of the day, so e. g. setting this to 720 (12 hours) will .
    | To ensure payers have a minimum of time to do their turn, see:
    | NOVUSORDO_MINIMUM_DELAY_BEFORE_TURN_EXPIRATION_MINUTES
    | Default value is 1440, one day.
    |
    */

    'turn_time_limit_minutes' => env('NOVUSORDO_TURN_TIME_LIMIT_MINUTES', 1440),
];
