<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Always render Javascript inline
    |--------------------------------------------------------------------------
    |
    | Setting this to true will force static Javascript resources to always render
    | as inline Javascript code i. e. inside a script block instead of a script tag
    | with a src and no body. This is meant for development, where some changes might
    | not be committed yet and would go unnoticed.
    |
    */

    // 'name' => env('NOVUSORDO_ALWAYS_RENDER_JS_INLINE', false),

    /*
    |--------------------------------------------------------------------------
    | Always render Javascript
    |--------------------------------------------------------------------------
    |
    | Setting this to true will force the application to always rerender static
    | Javascript resources before linking them the first time they're accessed.
    | This is meant for development, where some changes might not be committed
    | yet and would go unnoticed by caching.
    |
    */

    'always_render_js' => env('NOVUSORDO_ALWAYS_RENDER_JS', false),

];
