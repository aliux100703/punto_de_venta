<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#122d32"><title>InnovateCode POS</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="auth-app"><div class="auth-layout">
<aside class="auth-story"><a class="app-brand" href="/"><span class="brand-mark"><x-icon name="layers" size="25" /></span>Innovate<span class="brand-light">Code</span></a><div class="auth-story-content"><span class="eyebrow">UN NUEVO CAPÍTULO PARA TU NEGOCIO</span><h1>Todo conectado.<br>Todo más claro.<br><span>Todo por crecer.</span></h1><p>Las herramientas que necesitas para vender, organizar y tomar mejores decisiones. En un solo espacio.</p><div class="auth-story-features"><span><x-icon name="check" size="13" /> En español</span><span><x-icon name="wallet" size="13" /> Pesos mexicanos</span></div></div><div class="auth-story-footer">© {{ now()->year }} InnovateCode POS · Hecho para tu negocio.</div></aside>
<main class="auth-main"><div class="auth-card"><a class="auth-mobile-brand" href="/">InnovateCode POS</a><span class="eyebrow">MENOS COMPLICACIONES, MÁS POSIBILIDADES</span><h2>Tu negocio, en buenas manos.</h2><p class="auth-subtitle">Una forma más clara de llevar cada día de trabajo.</p>
<div class="welcome-feature"><x-icon name="cart" size="21" /><span><strong>Una mejor experiencia de venta</strong><small>Cobra, cotiza y conecta con tus clientes.</small></span></div>
<div class="welcome-feature"><x-icon name="box" size="21" /><span><strong>Cada producto bajo control</strong><small>Conoce tus existencias y registra cada movimiento.</small></span></div>
<div class="welcome-feature"><x-icon name="shield" size="21" /><span><strong>Tu equipo, bien organizado</strong><small>Cada persona con el acceso que necesita.</small></span></div>
<div class="welcome-actions">
    @auth
        <a href="{{ route('dashboard') }}" class="btn btn-primary">Entrar a mi espacio <x-icon name="arrow" size="15" /></a>
    @else
        <a href="{{ route('login') }}" class="btn btn-primary">Iniciar sesión <x-icon name="arrow" size="15" /></a>
        @if(!\App\Models\User::exists())
            <a href="{{ route('register') }}">Comenzar · Crear mi primera cuenta</a>
        @endif
    @endauth
</div></div></main></div></body>
</html>