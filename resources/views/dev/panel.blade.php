@php
    use App\Models\User;
@endphp
@php($page_title = "Novus Ordo Core - Development panel")
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $page_title }}</title>
        <script src="{{ asset('js/jquery-3.7.1.min.js') }}"></script>
    </head>
    <body>
        <script>
            {{ $js_dev_client_services }}
            let devServices = new DevPanelServices(@json(url("")), @json(csrf_token()));
            {{ $js_client_services }}
            let services = new NovusOrdoServices(@json(url("")), @json(csrf_token()));

            let users = @json($users->map(fn (User $user) => $user->exportForDevPanel())->all());
            var setPasswordUserOrNull = null;
            
            $(document).ready(function(){
                var userLinks = [];
                users.forEach(user => {
                    userLinks.push(`<a href="javascript:void(0)" onclick="prepareSetPassword(${user.user_id})">${user.username}</a>`);
                });
                $("#user_list_set_password").html(userLinks.join('&nbsp'));
            });

            function prepareSetPassword(userId) {
                let user = users.find(user => user.user_id === userId);
                setPasswordUserOrNull = user;
                $("#user_set_password_username").html(user.username);
                $("#user_set_password").show();
                $("#user_set_password_ok_message").hide();
            }

            function setPasswordForSelectedUser() {
                $("#user_set_password").hide();

                $.post({
                    url: @json(route('dev.ajax.set-user-password')),
                    data: {
                        _token: @json(csrf_token()),
                        user_id: setPasswordUserOrNull.user_id,
                        new_password: $("#user_set_password_password").val()
                    }
                })
                .done(function() {
                    $("#error_messages").html("");
                    $("#user_set_password_ok_message_text").html(`<div>Changed password for user ${setPasswordUserOrNull.username}!</div>`);
                    $("#user_set_password_ok_message").show();
                })
                .fail(function(data) {
                    $("#user_set_password").show();
                    $("#error_messages").html(`<li style="color: crimson">${JSON.stringify(data.responseJSON)}}</li>`);
                });
                $("#user_set_password_password").val("");
            }
            function setRandomPasswordForSelectedUser() {
                $("#user_set_password").hide();
                $("#user_set_password_password").val("");

                $.get({
                    url: @json(route('dev.ajax.generate-password'))
                })
                .done(function(data) {
                    $.post({
                        url: @json(route('dev.ajax.set-user-password')),
                        data: {
                            _token: @json(csrf_token()),
                            user_id: setPasswordUserOrNull.user_id,
                            new_password: data.password
                        }
                    })
                    .done(function() {
                        $("#error_messages").html("");
                        $("#user_set_password_ok_message_text").html(`<div>Changed password for user ${setPasswordUserOrNull.username}! New password: <span id="new-password">${data.password}</span>${renderCopyToClipboardButton("new-password")}</div>`);
                        $("#user_set_password_ok_message").show();
                    })
                    .fail(function(data) {
                        $("#user_set_password").show();
                        $("#error_messages").html(`<li style="color: crimson">${JSON.stringify(data.responseJSON)}}</li>`);
                    });
                })
            }
        </script>
        <h1>
        {{ $page_title }}
        </h1>
        <x-error />
        <x-copy-to-clipboard-button />
        <div>
            Active game ID: {{$game_id}}
            <form method="post" enctype="multipart/form-data" action="{{route('dev.start-game')}}">
                @csrf
                <button class="btn btn-primary" type="submit">Start a new game</button>
            </form>
        </div>
        <div>
            Current turn: #{{$turn_number}}
            <form method="post" enctype="multipart/form-data" action="{{route('dev.next-turn')}}">
                @csrf
                <button class="btn btn-primary" type="submit">Next turn</button>
            </form>
            <form method="post" enctype="multipart/form-data" action="{{route('dev.rollback-turn')}}">
                @csrf
                <button class="btn btn-primary" type="submit">Rollback last turn</button>
            </form>
        </div>
        <h2>
        Users
        </h2>
        <div>
            <h3>Go to dashboard</h3>
            @foreach ($users as $user)
                <a href="{{route('dev.login-user', ['user_id' => $user->getId()])}}">{{$user->getName()}}</a>&nbsp;
            @endforeach
        </div>
        <div>
            <h3>Add a new user</h3>
            <form method="post" enctype="multipart/form-data" action="{{route('dev.add-user')}}">
                @csrf
                User name: <input name="username" type="text"></text>
                <br>
                Password (will be randomized if left blank): <input name="password" type="password"></text>
                <br>
                <button class="btn btn-primary" type="submit">Add user</button>
            </form>
        </div>
        <div>
            <h3>Set password</h3>
            <div id="user_set_password_ok_message" style="display: none">
                <span style="color: green" id="user_set_password_ok_message_text" />
            </div>
            <div id="user_list_set_password"></div>
            <div id="user_set_password" style="display: none">
                <h4>Set password for user <span id="user_set_password_username"></span></h4>
                New password: <input id="user_set_password_password" type="password" autocomplete="off"></text> <a href="javascript:void(0)" onclick="setPasswordForSelectedUser()">set password</a> <a href="javascript:void(0)" onclick="setRandomPasswordForSelectedUser()">set a random password</a>
            </div>
        </div>
        <div>
            <h3>Javascript client services</h3>
            <a href="{{@route('dev.generate-js-client-services')}}">Generate Javascript client services code</a>
        </div>
        <div>
            <h3>Go to test SPA</h3>
            @foreach ($users as $user)
                <a href="{{route('dev.spa', ["userId" => $user->getId()])}}">{{$user->getName()}}</a>&nbsp;
            @endforeach
        </div>
    </body>
</html>
