<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Laravel') }}</title>
    </head>
    <body>
        @if(!$adminExists)
            There is no administrator provisioned. Run command '<b>php artisan provision-admin ADMIN_NAME</b>'
        @else
        <x-dev-mode />
        @endif
        <x-error />
        <form method="post" enctype="multipart/form-data" action="{{route('user.login')}}">
            @csrf
            <table>
                <tr><td>User</td><td><input name="username" type="text" value="{{ old('username') }}"></td></tr>
                <tr><td>Password</td><td><input name="password" type="password"></td></tr>
            </table>
            <button type="submit">Login</button>
        </form>
    </body>
</html>
