<x-admin-layout title="Inventario">

    <x-slot name="header">
        <h2 class="font-semibold text-xl">
            Inventario
        </h2>
    </x-slot>

    <div class="py-4">

        <div class="container">

            @if(session('success'))

                <div class="alert alert-success">
                    {{ session('success') }}
                </div>

            @endif


            <div class="card">

                <div class="card-body">

                    <form
                        method="GET"
                        action="{{ route('inventory.index') }}"
                        class="row mb-4"
                    >

                        <div class="col-md-8">

                            <input
                                type="text"
                                name="search"
                                class="form-control"
                                placeholder="Buscar producto, SKU o código de barras"
                                value="{{ $search }}"
                                autofocus
                            >

                        </div>


                        <div class="col-md-4">

                            <button
                                type="submit"
                                class="btn btn-primary"
                            >
                                Buscar
                            </button>


                            <a
                                href="{{ route('inventory.index') }}"
                                class="btn btn-secondary"
                            >
                                Limpiar
                            </a>

                        </div>

                    </form>


                    <div class="table-responsive">

                        <table class="table table-striped table-hover">

                            <thead>

                                <tr>

                                    <th>Producto</th>

                                    <th>SKU</th>

                                    <th>Categoría</th>

                                    <th>Unidad</th>

                                    <th>Existencia</th>

                                    <th>Stock mínimo</th>

                                    <th>Estado</th>

                                    <th>Acciones</th>

                                </tr>

                            </thead>


                            <tbody>

                                @forelse($products as $product)

                                    <tr>

                                        <td>
                                            {{ $product->name }}
                                        </td>


                                        <td>
                                            {{ $product->sku ?? '-' }}
                                        </td>


                                        <td>
                                            {{ $product->category?->name ?? '-' }}
                                        </td>


                                        <td>

                                            {{ $product->unit?->name }}

                                            @if($product->unit)
                                                ({{ $product->unit->abbreviation }})
                                            @endif

                                        </td>


                                        <td>

                                            <strong>
                                                {{ number_format(
                                                    $product->current_stock,
                                                    3
                                                ) }}
                                            </strong>

                                        </td>


                                        <td>

                                            {{ number_format(
                                                $product->minimum_stock,
                                                3
                                            ) }}

                                        </td>


                                        <td>

                                            @if($product->current_stock <= 0)

                                                <span class="badge bg-danger">
                                                    Sin existencia
                                                </span>

                                            @elseif(
                                                $product->current_stock
                                                <=
                                                $product->minimum_stock
                                            )

                                                <span class="badge bg-warning text-dark">
                                                    Stock bajo
                                                </span>

                                            @else

                                                <span class="badge bg-success">
                                                    Disponible
                                                </span>

                                            @endif

                                        </td>


                                        <td>

                                            <a
                                                href="{{ route(
                                                    'inventory.show',
                                                    $product
                                                ) }}"
                                                class="btn btn-sm btn-info"
                                            >
                                                Historial
                                            </a>


                                            @can('manage-inventory')
<a
                                                href="{{ route(
                                                    'inventory.movement',
                                                    $product
                                                ) }}"
                                                class="btn btn-sm btn-primary"
                                            >
                                                Movimiento
                                            </a>
@endcan

                                        </td>

                                    </tr>

                                @empty

                                    <tr>

                                        <td
                                            colspan="8"
                                            class="text-center"
                                        >
                                            No se encontraron productos.
                                        </td>

                                    </tr>

                                @endforelse

                            </tbody>

                        </table>

                    </div>


                    {{ $products->links() }}

                </div>

            </div>

        </div>

    </div>

</x-admin-layout>