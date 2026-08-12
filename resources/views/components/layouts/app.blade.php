<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Usina Docs: plataforma aberta para documentação e aprendizado.">
    <title>{{ $title ?? 'Usina Docs' }}</title>
    <style>
        :root { color-scheme: light; --ink:#172033; --muted:#61708a; --line:#dce3ed; --paper:#fff; --bg:#f6f8fb; --brand:#0068a8; --accent:#f58220; }
        * { box-sizing:border-box; } body { margin:0; color:var(--ink); background:var(--bg); font:16px/1.6 system-ui,-apple-system,"Segoe UI",sans-serif; }
        a { color:inherit; } .wrap { max-width:1024px; margin:auto; padding:0 24px; } header { background:var(--paper); border-bottom:1px solid var(--line); } nav { min-height:68px; display:flex; align-items:center; justify-content:space-between; gap:24px; } .brand { text-decoration:none; font-size:1.18rem; font-weight:800; letter-spacing:-.03em; } .brand span { color:var(--brand); } nav a:not(.brand) { color:var(--brand); font-weight:700; text-decoration:none; } main { padding:72px 0; } .eyebrow { color:var(--brand); font-size:.82rem; font-weight:800; letter-spacing:.1em; text-transform:uppercase; } h1 { max-width:770px; margin:10px 0 18px; font-size:clamp(2.2rem,6vw,4.4rem); line-height:1.02; letter-spacing:-.055em; } h2 { margin:0 0 8px; font-size:1.3rem; } .lead { max-width:720px; color:var(--muted); font-size:1.2rem; } .actions { display:flex; flex-wrap:wrap; gap:12px; margin-top:30px; } .button { display:inline-block; padding:11px 16px; border-radius:8px; background:var(--brand); color:white; font-weight:800; text-decoration:none; } .button.alt { background:transparent; color:var(--brand); border:1px solid var(--brand); } .grid { display:grid; grid-template-columns:repeat(3,1fr); gap:18px; margin-top:56px; } .card { padding:22px; background:var(--paper); border:1px solid var(--line); border-radius:12px; } .card p { margin:0; color:var(--muted); } .meta { color:var(--muted); font-size:.9rem; } .article { max-width:760px; } .article header { padding-bottom:26px; background:transparent; border:0; } .block { margin-top:18px; padding:24px; background:var(--paper); border:1px solid var(--line); border-radius:12px; } .block p { margin:0; color:var(--muted); } pre { overflow:auto; margin:0; padding:18px; border-radius:8px; background:#172033; color:#e8f0fc; font:14px/1.55 ui-monospace,SFMono-Regular,Consolas,monospace; } footer { padding:28px 0; border-top:1px solid var(--line); color:var(--muted); font-size:.9rem; } @media (max-width:700px) { main { padding:44px 0; } .grid { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<header><nav class="wrap"><a class="brand" href="{{ url('/') }}">Usina <span>Docs</span></a><a href="{{ route('pages.show', ['locale' => 'pt', 'slug' => 'bem-vindo']) }}">Ver demonstração</a></nav></header>
<main class="wrap">{{ $slot }}</main>
<footer><div class="wrap">Usina Docs · Pré-alpha · Código sob MPL-2.0</div></footer>
</body>
</html>
