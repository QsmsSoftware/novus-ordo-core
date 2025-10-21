<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Test SPA</title>
        <script src="{{ asset('js/jquery-3.7.1.min.js') }}"></script>
    </head>
    <body>
        <script>
            {!! $js_client_services !!}
            let services = new NovusOrdoServices(@json(url("")), @json(csrf_token()));
        </script>
        This is the test SPA page. You can use it to test ajax calls using the JS client in the Javascript console. For example:
        <pre>
            await services.getUserInfo() // Get information about logged in user.
        </pre>
        <a href="{{route('dev.panel')}}">Return to the development panel</a>
    </body>
</html>