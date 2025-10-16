<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Laravel') }}</title>
    </head>
    <body>
        <div>
        <b>Create your nation</b>
        </div>
        <br>
        <x-error />
        <div>
            <form method="post" enctype="multipart/form-data" action="{{route('nation.store')}}">
                @csrf
                Nation's name:
                <input type="text" name="name" class="form-control">
                <br>
                <button class="btn btn-primary" type="submit">Create nation</button>
            </form>
        </div>
    </body>
</html>
