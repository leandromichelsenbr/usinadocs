<x-layouts.app title="Entrar — Usina Docs">
    <section class="card" style="max-width: 480px">
        <p class="eyebrow">Área administrativa</p>
        <h1>Entrar</h1>
        <p class="lead">Use a conta administrativa criada para esta instalação.</p>
        <form method="post" action="{{ route('login.store') }}">
            @csrf
            <p><label for="email">E-mail</label><br><input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email"></p>
            @error('email')<p style="color:#a11f1f">{{ $message }}</p>@enderror
            <p><label for="password">Senha</label><br><input id="password" name="password" type="password" required autocomplete="current-password"></p>
            <p><label><input name="remember" type="checkbox" value="1"> Permanecer conectado neste dispositivo</label></p>
            <p><button class="button" type="submit">Entrar</button></p>
        </form>
    </section>
</x-layouts.app>
