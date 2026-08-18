<x-app-layout>

    <x-slot name="header">

        <h2 class="font-semibold text-xl">
            Movimiento de inventario
        </h2>

    </x-slot>


    <div class="py-4">

        <div class="container">


            <div class="card mb-4">

                <div class="card-body">

                    <h4>
                        {{ $product->name }}
                    </h4>


                    <div>
                        SKU:
                        <strong>
                            {{ $product->sku ?? '-' }}
                        </strong>
                    </div>


                    <div>
                        Código:
                        <strong>
                            {{ $product->barcode ?? '-' }}
                        </strong>
                    </div>


                    <div class="mt-2">

                        Existencia actual:

                        <strong class="fs-4">

                            {{ number_format(
                                $product->current_stock,
                                3
                            ) }}

                            {{ $product->unit?->abbreviation }}

                        </strong>

                    </div>

                </div>

            </div>


            @if($errors->has('inventory'))

                <div class="alert alert-danger">

                    {{ $errors->first('inventory') }}

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


            <div class="card">

                <div class="card-body">

                    <form
                        method="POST"
                        action="{{ route(
                            'inventory.movement.store',
                            $product
                        ) }}"
                    >

                        @csrf


                        <div class="mb-3">

                            <label class="form-label">
                                Tipo de movimiento *
                            </label>


                            <select
                                name="type"
                                class="form-select"
                                required
                            >

                                <option value="">
                                    Seleccionar
                                </option>


                                <option
                                    value="ENTRADA"
                                    @selected(
                                        old('type') === 'ENTRADA'
                                    )
                                >
                                    Entrada de mercancía
                                </option>


                                <option
                                    value="AJUSTE_POSITIVO"
                                    @selected(
                                        old('type')
                                        ===
                                        'AJUSTE_POSITIVO'
                                    )
                                >
                                    Ajuste positivo
                                </option>


                                <option
                                    value="AJUSTE_NEGATIVO"
                                    @selected(
                                        old('type')
                                        ===
                                        'AJUSTE_NEGATIVO'
                                    )
                                >
                                    Ajuste negativo
                                </option>


                                <option
                                    value="MERMA"
                                    @selected(
                                        old('type') === 'MERMA'
                                    )
                                >
                                    Merma
                                </option>

                            </select>

                        </div>


                        <div class="mb-3">

                            <label class="form-label">
                                Cantidad *
                            </label>


                            <input
                                type="number"
                                name="quantity"
                                class="form-control"
                                min="1"
                                step="{{ $product->unit?->allows_decimals ? '0.001' : '1' }}"
                                value="{{ old('quantity') }}"
                                required
                            >


                            <small class="text-muted">

                                Unidad:

                                {{ $product->unit?->name }}

                                ({{ $product->unit?->abbreviation }})

                            </small>

                        </div>


                        <div class="mb-3">

                            <label class="form-label">
                                Motivo *
                            </label>


                            <textarea
                                name="reason"
                                class="form-control"
                                rows="3"
                                required
                            >{{ old('reason') }}</textarea>

                        </div>


                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            Registrar movimiento
                        </button>


                        <a
                            href="{{ route(
                                'inventory.show',
                                $product
                            ) }}"
                            class="btn btn-secondary"
                        >
                            Cancelar
                        </a>

                    </form>

                </div>

            </div>

        </div>

    </div>

</x-app-layout>