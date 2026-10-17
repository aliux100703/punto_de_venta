<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><meta name="theme-color" content="#122d32"><title>{{ config('app.name') }}</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="auth-app">
<div class="auth-layout">
    <aside class="auth-story"><a class="app-brand" href="/"><span class="brand-mark"><x-icon name="layers" size="25" /></span>Innovate<span class="brand-light">Code</span></a><div class="auth-story-content"><span class="eyebrow">TU NEGOCIO TIENE UN GRAN FUTURO</span><h1>Más control.<br>Más tiempo.<br><span>Más posibilidades.</span></h1><p>Tu punto de venta, inventario y equipo. Conectados para que tú te enfoques en lo que mejor haces.</p><div class="auth-story-features"><span><x-icon name="cart" size="14" /> Ventas</span><span><x-icon name="box" size="14" /> Inventario</span><span><x-icon name="trend" size="14" /> Resultados</span></div></div><div class="auth-story-footer">© {{ now()->year }} InnovateCode POS · Hecho para tu negocio.</div></aside>
    <main class="auth-main"><div class="auth-card"><a class="auth-mobile-brand" href="/">InnovateCode POS</a><span class="eyebrow">BIENVENIDO A TU ESPACIO</span>
        <h2>{{ request()->routeIs('login') ? 'Qué bueno verte de nuevo.' : (request()->routeIs('register') ? 'Tu negocio empieza aquí.' : 'Cuidamos de tu cuenta.') }}</h2>
        <p class="auth-subtitle">{{ request()->routeIs('login') ? 'Inicia sesión y continúa con un gran día de trabajo.' : 'Un paso más para entrar a tu espacio de trabajo.' }}</p>
        {{ $slot }}
        <div class="auth-security"><x-icon name="shield" size="13" /> Tu espacio de trabajo, siempre protegido.</div>
    </div></main>
</div>
</body>
</html>