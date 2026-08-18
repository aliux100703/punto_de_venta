<x-app-layout>

    <x-slot name="header">

        <h2 class="font-semibold text-xl">
            Historial de inventario
        </h2>

    </x-slot>


    <div class="py-4">

        <div class="container">


            @if(session('success'))

                <div class="alert alert-success">
                    {{ session('success') }}
                </div>

            @endif


            <div class="card mb-4">

                <div class="card-body">

                    <div class="row">

                        <div class="col-md-8">

                            <h4>
                                {{ $product->name }}
                            </h4>


                            <p class="mb-1">
                                SKU:
                                {{ $product->sku ?? '-' }}
                            </p>


                            <p class="mb-1">

                                Categoría:

                                {{ $product->category?->name ?? '-' }}

                            </p>


                            <p class="mb-0">

                                Marca:

                                {{ $product->brand?->name ?? '-' }}

                            </p>

                        </div>


                        <div class="col-md-4 text-end">

                            <div>
                                Existencia
                            </div>


                            <div class="fs-2 fw-bold">

                                {{ number_format(
                                    $product->current_stock,
                                    3
                                ) }}

                                {{ $product->unit?->abbreviation }}

                            </div>


                            <a
                                href="{{ route(
                                    'inventory.movement',
                                    $product
                                ) }}"
                                class="btn btn-primary mt-2"
                            >
                                Nuevo movimiento
                            </a>

                        </div>

                    </div>

                </div>

            </div>


            <div class="card">

                <div class="card-body">

                    <div class="table-responsive">

                        <table class="table table-striped">

                            <thead>

                                <tr>

                                    <th>Fecha</th>

                                    <th>Tipo</th>

                                    <th>Cantidad</th>

                                    <th>Anterior</th>

                                    <th>Nuevo</th>

                                    <th>Motivo</th>

                                    <th>Usuario</th>

                                </tr>

                            </thead>


                            <tbody>

                                @forelse($movements as $movement)

                                    <tr>

                                        <td>

                                            {{ $movement
                                                ->created_at
                                                ->format(
                                                    'd/m/Y H:i'
                                                )
                                            }}

                                        </td>


                                        <td>

                                            {{ str_replace(
                                                '_',
                                                ' ',
                                                $movement->type
                                            ) }}

                                        </td>


                                        <td>

                                            {{ number_format(
                                                $movement->quantity,
                                                3
                                            ) }}

                                        </td>


                                        <td>

                                            {{ number_format(
                                                $movement->previous_stock,
                                                3
                                            ) }}

                                        </td>


                                        <td>

                                            <strong>

                                                {{ number_format(
                                                    $movement->new_stock,
                                                    3
                                                ) }}

                                            </strong>

                                        </td>


                                        <td>

                                            {{ $movement->reason }}

                                        </td>


                                        <td>

                                            {{ $movement->user?->name ?? '-' }}

                                        </td>

                                    </tr>

                                @empty

                                    <tr>

                                        <td
                                            colspan="7"
                                            class="text-center"
                                        >
                                            Este producto todavía no tiene movimientos.
                                        </td>

                                    </tr>

                                @endforelse

                            </tbody>

                        </table>

                    </div>


                    {{ $movements->links() }}


                    <a
                        href="{{ route('inventory.index') }}"
                        class="btn btn-secondary"
                    >
                        Volver al inventario
                    </a>

                </div>

            </div>

        </div>

    </div>

</x-app-layout>