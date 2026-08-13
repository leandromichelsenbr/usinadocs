<x-layouts.app title="Entrar — Usina Docs">
    <section class="panel" style="max-width: 500px">
        <p class="eyebrow">Área administrativa</p><h1>Entrar</h1><p class="lead">Use a conta administrativa criada para esta instalação.</p>
        <form method="post" action="{{ route('login.store') }}">@csrf
            <p><label for="email">E-mail</label><input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email"></p>
            @error('email')<p class="notice">{{ $message }}</p>@enderror
            <p><label for="password">Senha</label><input id="password" name="password" type="password" required autocomplete="current-password"></p>
            <p><label><input name="remember" type="checkbox" value="1" style="width:auto"> Permanecer conectado neste dispositivo</label></p>
            <p><button class="button" type="submit">Entrar</button></p>
        </form>
    </section>
</x-layouts.app>
