<x-admin-layout title="Caja">

    <div class="row g-4">

        <div class="col-lg-8">

            @if($cashRegister)

                <div class="card mb-4">

                    <div class="card-body">

                        <div class="d-flex justify-content-between align-items-center mb-4">

                            <div>

                                <h4 class="mb-1">
                                    Caja abierta
                                </h4>

                                <span class="text-muted">
                                    Apertura:
                                    {{ $cashRegister->opened_at->format('d/m/Y H:i') }}
                                </span>

                            </div>

                            <span class="badge bg-success fs-6">
                                ABIERTA
                            </span>

                        </div>


                        <div class="row g-3">

                            <div class="col-md-6">

                                <div class="border rounded p-3">

                                    <span class="text-muted">
                                        Fondo inicial
                                    </span>

                                    <div class="fs-4 fw-bold">
                                        ${{ number_format($summary['opening'], 2) }}
                                    </div>

                                </div>

                            </div>


                            <div class="col-md-6">

                                <div class="border rounded p-3">

                                    <span class="text-muted">
                                        Entradas
                                    </span>

                                    <div class="fs-4 fw-bold text-success">
                                        ${{ number_format($summary['income'], 2) }}
                                    </div>

                                </div>

                            </div>


                            <div class="col-md-6">

                                <div class="border rounded p-3">

                                    <span class="text-muted">
                                        Salidas
                                    </span>

                                    <div class="fs-4 fw-bold text-danger">
                                        ${{ number_format($summary['expense'], 2) }}
                                    </div>

                                </div>

                            </div>


                            <div class="col-md-6">

                                <div class="border rounded p-3">

                                    <span class="text-muted">
                                        Efectivo esperado
                                    </span>

                                    <div class="fs-4 fw-bold">
                                        ${{ number_format($summary['expected'], 2) }}
                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                <div class="card">

                    <div class="card-body">

                        <h5 class="mb-3">
                            Movimientos
                        </h5>

                        <div class="table-responsive">

                            <table class="table align-middle">

                                <thead>

                                    <tr>
                                        <th>Fecha</th>
                                        <th>Concepto</th>
                                        <th>Tipo</th>
                                        <th class="text-end">
                                            Monto
                                        </th>
                                    </tr>

                                </thead>

                                <tbody>

                                    @forelse(
                                        $cashRegister->movements
                                            ->sortByDesc('created_at')
                                        as $movement
                                    )

                                        <tr>

                                            <td>
                                                {{ $movement->created_at->format('d/m/Y H:i') }}
                                            </td>

                                            <td>
                                                {{ $movement->concept }}
                                            </td>

                                            <td>

                                                @if($movement->type === 'ENTRADA')

                                                    <span class="badge bg-success">
                                                        Entrada
                                                    </span>

                                                @else

                                                    <span class="badge bg-danger">
                                                        Salida
                                                    </span>

                                                @endif

                                            </td>

                                            <td class="text-end fw-bold">

                                                @if($movement->type === 'ENTRADA')
                                                    +
                                                @else
                                                    -
                                                @endif

                                                ${{ number_format($movement->amount, 2) }}

                                            </td>

                                        </tr>

                                    @empty

                                        <tr>

                                            <td
                                                colspan="4"
                                                class="text-center text-muted py-4"
                                            >
                                                No hay movimientos.
                                            </td>

                                        </tr>

                                    @endforelse

                                </tbody>

                            </table>

                        </div>

                    </div>

                </div>

            @else

                <div class="card">

                    <div class="card-body">

                        <div class="text-center py-5">

                            <h3>
                                Caja cerrada
                            </h3>

                            <p class="text-muted">
                                Debes abrir la caja para comenzar el turno.
                            </p>

                        </div>

                    </div>

                </div>

            @endif

        </div>


        <div class="col-lg-4">

            @if(!$cashRegister)

                <div class="card">

                    <div class="card-body">

                        <h5 class="mb-4">
                            Abrir caja
                        </h5>

                        <form
                            method="POST"
                            action="{{ route('cash.open') }}"
                        >

                            @csrf

                            <div class="mb-3">

                                <label class="form-label">
                                    Fondo inicial
                                </label>

                                <input
                                    type="number"
                                    name="opening_amount"
                                    class="form-control form-control-lg"
                                    min="0"
                                    step="0.01"
                                    value="{{ old('opening_amount', 0) }}"
                                    required
                                >

                                @error('opening_amount')
                                    <div class="text-danger small mt-1">
                                        {{ $message }}
                                    </div>
                                @enderror

                            </div>


                            <div class="mb-3">

                                <label class="form-label">
                                    Notas
                                </label>

                                <textarea
                                    name="notes"
                                    class="form-control"
                                    rows="3"
                                >{{ old('notes') }}</textarea>

                            </div>


                            <button
                                type="submit"
                                class="btn btn-success btn-lg w-100"
                            >
                                Abrir caja
                            </button>

                        </form>

                    </div>

                </div>

            @else

                <div class="card position-sticky" style="top:20px;">

                    <div class="card-body">

                        <h5>
                            Cierre de caja
                        </h5>

                        <p class="text-muted">
                            Captura el efectivo que tienes físicamente.
                        </p>


                        <div class="alert alert-light border">

                            Efectivo esperado

                            <strong class="float-end">
                                ${{ number_format($summary['expected'], 2) }}
                            </strong>

                        </div>


                        @if(auth()->user()->hasRole('Vendedor'))
                        <a class="btn btn-danger w-100" href="{{ route('shift.end') }}">Cerrar caja y finalizar sesión</a>
                        @else
                        <form
                            method="POST"
                            action="{{ route('cash.close') }}"
                        >

                            @csrf

                            <div class="mb-3">

                                <label class="form-label">
                                    Efectivo contado
                                </label>

                                <input
                                    type="number"
                                    name="counted_cash"
                                    class="form-control form-control-lg"
                                    min="0"
                                    step="0.01"
                                    required
                                >

                                @error('counted_cash')

                                    <div class="text-danger small mt-1">
                                        {{ $message }}
                                    </div>

                                @enderror

                            </div>


                            <div class="mb-3">

                                <label class="form-label">
                                    Observaciones
                                </label>

                                <textarea
                                    name="notes"
                                    class="form-control"
                                    rows="3"
                                ></textarea>

                            </div>


                            <button
                                type="submit"
                                class="btn btn-danger btn-lg w-100"
                                onclick="return confirm(
                                    '¿Seguro que deseas cerrar la caja?'
                                )"
                            >
                                Cerrar caja
                            </button>

                        </form>
                        @endif

                    </div>

                </div>

            @endif

        </div>

    </div>


    @if($history->count())

        <div class="card mt-4">

            <div class="card-body">

                <h5 class="mb-3">
                    Últimos cierres
                </h5>

                <div class="table-responsive">

                    <table class="table">

                        <thead>

                            <tr>
                                <th>Apertura</th>
                                <th>Cierre</th>
                                <th>Inicial</th>
                                <th>Esperado</th>
                                <th>Contado</th>
                                <th>Diferencia</th>
                            </tr>

                        </thead>

                        <tbody>

                            @foreach($history as $register)

                                <tr>

                                    <td>
                                        {{ $register->opened_at?->format('d/m/Y H:i') }}
                                    </td>

                                    <td>
                                        {{ $register->closed_at?->format('d/m/Y H:i') }}
                                    </td>

                                    <td>
                                        ${{ number_format($register->opening_amount, 2) }}
                                    </td>

                                    <td>
                                        ${{ number_format($register->expected_cash, 2) }}
                                    </td>

                                    <td>
                                        ${{ number_format($register->counted_cash, 2) }}
                                    </td>

                                    <td>

                                        @if($register->difference > 0)

                                            <span class="text-success">
                                                +${{ number_format($register->difference, 2) }}
                                            </span>

                                        @elseif($register->difference < 0)

                                            <span class="text-danger">
                                                -${{ number_format(abs($register->difference), 2) }}
                                            </span>

                                        @else

                                            $0.00

                                        @endif

                                    </td>

                                </tr>

                            @endforeach

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    @endif

</x-admin-layout>