<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $title ?? 'InnovateCode POS' }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body>

<div class="d-flex">

    <aside
        class="bg-dark text-white min-vh-100 p-3"
        style="width: 250px;"
    >

        <h4 class="mb-4">
            InnovateCode POS
        </h4>

        <ul class="nav nav-pills flex-column gap-2">

            <li class="nav-item">
                <a
                    href="{{ route('dashboard') }}"
                    class="nav-link text-white"
                >
                    Dashboard
                </a>
            </li>

            <li class="nav-item">
            <a
    href="{{ route('pos.index') }}"
    class="nav-link text-white"
>
    Punto de Venta
</a>
            </li>

            <li class="nav-item">
                <a
                    href="{{ route('products.index') }}"
                    class="nav-link text-white"
                >
                    Productos
                </a>
            </li>

            <li class="nav-item">
                <a
                    href="{{ route('inventory.index') }}"
                    class="nav-link text-white"
                >
                    Inventario
                </a>
            </li>

            <li class="nav-item">
                <a
                    href="#"
                    class="nav-link text-white"
                >
                    Ventas
                </a>
            </li>

            <li class="nav-item">
                <a
                    href="#"
                    class="nav-link text-white"
                >
                    Cotizaciones
                </a>
            </li>

            <li class="nav-item">
                <a
                    href="#"
                    class="nav-link text-white"
                >
                    Clientes
                </a>
            </li>

            <hr>

            <li class="nav-item">
                <a
                    href="#"
                    class="nav-link text-white"
                >
                    Caja
                </a>
            </li>

            <li class="nav-item">
                <a
                    href="#"
                    class="nav-link text-white"
                >
                    Gastos
                </a>
            </li>

        </ul>

    </aside>


    <main class="flex-grow-1">

        <nav class="navbar navbar-light bg-white border-bottom px-4">

            <span class="navbar-brand mb-0 h1">
                {{ $title ?? 'Panel' }}
            </span>

            <div class="dropdown">

                <button
                    class="btn btn-outline-secondary dropdown-toggle"
                    data-bs-toggle="dropdown"
                >
                    {{ auth()->user()->name }}
                </button>

                <ul class="dropdown-menu dropdown-menu-end">

                    <li>
                        <form
                            method="POST"
                            action="{{ route('logout') }}"
                        >
                            @csrf

                            <button
                                type="submit"
                                class="dropdown-item"
                            >
                                Cerrar sesión
                            </button>

                        </form>
                    </li>

                </ul>

            </div>

        </nav>


        <div class="container-fluid p-4">

            @if(session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger">

                    <ul class="mb-0">

                        @foreach($errors->all() as $error)

                            <li>
                                {{ $error }}
                            </li>

                        @endforeach

                    </ul>

                </div>
            @endif


            {{ $slot }}

        </div>

    </main>

</div>

</body>
</html>