<x-admin-layout title="Productos">
    <x-slot name="header">
        <div class="d-flex justify-content-between">

            <h2 class="font-semibold text-xl">
                Productos
            </h2>

            <a
                href="{{ route('products.create') }}"
                class="btn btn-primary"
            >
                Nuevo producto
            </a>

        </div>
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

                    <div class="table-responsive">

                        <table class="table table-striped table-hover">

                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Código</th>
                                    <th>Categoría</th>
                                    <th>Marca</th>
                                    <th>Precio</th>
                                    <th>Stock</th>
                                    <th>Estado</th>
                                    <th></th>
                                </tr>
                            </thead>

                            <tbody>

                                @forelse($products as $product)

                                    <tr>

                                        <td>
                                            {{ $product->name }}
                                        </td>

                                        <td>
                                            {{ $product->barcode ?? '-' }}
                                        </td>

                                        <td>
                                            {{ $product->category?->name ?? '-' }}
                                        </td>

                                        <td>
                                            {{ $product->brand?->name ?? '-' }}
                                        </td>

                                        <td>
                                            ${{ number_format(
                                                $product->sale_price,
                                                2
                                            ) }}
                                        </td>

                                        <td>
                                            {{ $product->current_stock }}

                                            {{ $product->unit?->abbreviation }}
                                        </td>

                                        <td>

                                            @if($product->active)

                                                <span class="badge bg-success">
                                                    Activo
                                                </span>

                                            @else

                                                <span class="badge bg-secondary">
                                                    Inactivo
                                                </span>

                                            @endif

                                        </td>

                                        <td>

                                            <a
                                                href="{{ route(
                                                    'products.edit',
                                                    $product
                                                ) }}"
                                                class="btn btn-sm btn-warning"
                                            >
                                                Editar
                                            </a>

                                        </td>

                                    </tr>

                                @empty

                                    <tr>
                                        <td
                                            colspan="8"
                                            class="text-center"
                                        >
                                            No hay productos registrados.
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

</x-app-layout>