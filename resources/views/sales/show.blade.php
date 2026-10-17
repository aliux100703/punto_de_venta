<x-admin-layout :title="'Venta '.$sale->folio">
    <div class="d-flex flex-wrap gap-2 mb-3 d-print-none"><a class="btn btn-outline-secondary" href="{{ route('sales.index') }}">Volver a ventas</a><button class="btn btn-primary" onclick="window.print()"><x-icon name="printer" size="16" /> Imprimir comprobante</button></div>
    <div class="card card-body mb-4"><div class="d-flex flex-wrap justify-content-between gap-3"><div><h1 class="h4">InnovateCode POS · {{ $sale->folio }}</h1><p class="small text-muted mb-2">{{ $sale->created_at->format('d/m/Y H:i') }}<br>Cliente: {{ $sale->customer?->name ?? 'Público general' }}<br>Atendió: {{ $sale->seller?->name }}</p></div><div><span class="status-pill {{ $sale->status === 'DEVOLUCION_PARCIAL' ? 'amber' : ($sale->status === 'COMPLETADA' ? '' : 'gray') }}">{{ str_replace('_', ' ', $sale->status) }}</span></div></div>
        <div class="table-responsive"><table class="table"><thead><tr><th>Producto</th><th>Vendidas</th><th>Devueltas</th><th>Precio original</th><th>IVA original</th><th>Total original</th></tr></thead><tbody>@foreach($sale->details as $line)<tr><td>{{ $line->product?->name }}</td><td>{{ $line->quantity }}</td><td>{{ $line->returned_quantity }}</td><td>${{ number_format($line->unit_price, 2) }}</td><td>${{ number_format($line->tax, 2) }}</td><td>${{ number_format($line->total, 2) }}</td></tr>@endforeach</tbody></table></div>
        <div class="text-end mt-4"><p>Subtotal original: ${{ number_format($sale->subtotal, 2) }} · IVA original: ${{ number_format($sale->taxes, 2) }}</p><p>Total original: <strong>${{ number_format($sale->total, 2) }}</strong></p><p>Reembolsado: <strong>${{ number_format($sale->refunded_amount, 2) }}</strong></p><p class="fs-4 fw-bold">Neto de la venta: ${{ number_format($sale->net_total, 2) }} MXN</p>@foreach($sale->payments as $payment)<p class="small text-muted">Pago original · {{ $payment->method_name ?? $payment->paymentMethod?->name }}: ${{ number_format($payment->amount, 2) }}</p>@endforeach</div><small class="text-muted">Los importes y pagos originales se conservan. Este comprobante no es una factura fiscal.</small>
    </div>
    <div class="card card-body mb-4 refund-policy"><strong>Política de devoluciones</strong><p class="small mb-1">{{ \App\Services\RefundPolicy::TEXT }}</p><small>La misma regla aplica a cancelaciones con reembolso.</small>@if($refundIsCash)<small>Fecha límite: {{ $sale->created_at->copy()->addHours(72)->format('d/m/Y H:i:s') }} (hora local).</small>@endif</div>
    @if($sale->returns->isNotEmpty())
    <section class="card mb-4"><div class="panel-heading"><div><h2>Historial de cancelaciones y devoluciones</h2><p>Reembolsos registrados para esta venta</p></div></div><div class="table-responsive"><table class="table"><thead><tr><th>Folio</th><th>Fecha</th><th>Operación / responsable</th><th>Motivo</th><th>Reembolso</th></tr></thead><tbody>@foreach($sale->returns as $adjustment)<tr><td><a href="{{ route('sale-returns.show', [$sale, $adjustment]) }}">{{ $adjustment->folio }}</a></td><td>{{ $adjustment->created_at->format('d/m/Y H:i') }}</td><td>{{ $adjustment->type }}<small class="d-block text-muted">{{ $adjustment->user?->name }}</small></td><td>{{ $adjustment->reason }}</td><td>${{ number_format($adjustment->total, 2) }}</td></tr>@endforeach</tbody></table></div></section>
    @endif
    @can('adjust', $sale)
        @if(in_array($sale->status, ['COMPLETADA', 'DEVOLUCION_PARCIAL']) && $refundBlockReason)
            <div class="alert alert-warning d-print-none">{{ $refundBlockReason }}</div>
        @endif
        @if(in_array($sale->status, ['COMPLETADA', 'DEVOLUCION_PARCIAL']) && ! $refundBlockReason)
        <section class="card card-body mb-4 d-print-none"><h2 class="h5">Registrar devolución</h2><p class="small text-muted">Indica solo las cantidades que recibes ahora. Los importes se calculan con los precios de la venta original. Desmarca «Reintegrar» si la mercancía no regresa al inventario disponible.</p>
            <form id="returnForm" action="{{ route('sales.returns.store', $sale) }}" method="POST" onsubmit="return confirm('¿Registrar esta devolución por ' + document.getElementById('refundEstimate').textContent + '? Revisa las cantidades y el reembolso antes de continuar.')">@csrf<input type="hidden" name="request_key" value="{{ old('request_key', (string) \Illuminate\Support\Str::uuid()) }}">
                <div class="table-responsive mb-3"><table class="table"><thead><tr><th>Producto</th><th>Disponible para devolver</th><th>Devolver ahora</th><th>Inventario</th></tr></thead><tbody>
                @foreach($sale->details as $line)
                    @php($remaining = max(0, round((float) $line->quantity - (float) $line->returned_quantity, 3)))
                    @if($remaining > 0)
                    <tr><td>{{ $line->product?->name }}<input type="hidden" name="items[{{ $loop->index }}][sale_detail_id]" value="{{ $line->id }}"></td><td>{{ number_format($remaining, 3) }}</td><td><input aria-label="Cantidad a devolver de {{ $line->product?->name }}" class="form-control return-quantity" style="max-width:130px" type="number" name="items[{{ $loop->index }}][quantity]" value="{{ old('items.'.$loop->index.'.quantity', 0) }}" min="0" max="{{ $remaining }}" step="{{ ($line->allows_decimals ?? $line->product?->unit?->allows_decimals) ? '0.001' : '1' }}" required data-sold="{{ (int) round((float) $line->quantity * 1000) }}" data-returned="{{ (int) round((float) $line->returned_quantity * 1000) }}" data-total="{{ (int) round((float) $line->total * 100) }}" data-refunded="{{ (int) round((float) $line->refunded_total * 100) }}"></td><td><input type="hidden" name="items[{{ $loop->index }}][restock]" value="0"><label class="d-flex gap-2 align-items-center small"><input type="checkbox" name="items[{{ $loop->index }}][restock]" value="1" @checked(old('items.'.$loop->index.'.restock', 1))> Reintegrar</label></td></tr>
                    @endif
                @endforeach
                </tbody></table></div>
                <div class="alert alert-light border d-flex justify-content-between"><span>Reembolso calculado</span><strong id="refundEstimate">$0.00</strong></div>
                <div class="mb-3"><label class="form-label" for="return-reason">Motivo de la devolución</label><textarea id="return-reason" name="reason" class="form-control" minlength="5" maxlength="1000" required rows="2" placeholder="Describe el motivo de la devolución">{{ old('reason') }}</textarea></div>
                @include('sale-returns.payment-fields', ['prefix' => 'return'])
                <button class="btn btn-primary">Registrar devolución</button>
            </form>
        </section>
        @if($sale->status === 'COMPLETADA' && $sale->returns->isEmpty())
        <section class="card card-body d-print-none"><h2 class="h5">Cancelar venta completa</h2><p class="small text-muted">Cancela toda la operación, reintegra todos los productos al inventario y registra un reembolso de <strong>${{ number_format($sale->total, 2) }}</strong>. Para devolver solo algunos productos utiliza el formulario anterior.</p>
            <form action="{{ route('sales.cancel', $sale) }}" method="POST" onsubmit="return confirm('¿Cancelar la venta completa, reintegrar todos los productos y registrar el reembolso de ${{ number_format($sale->total, 2) }}?')">@csrf<input type="hidden" name="request_key" value="{{ old('request_key', (string) \Illuminate\Support\Str::uuid()) }}"><div class="mb-3"><label class="form-label" for="cancel-reason">Motivo de cancelación</label><textarea id="cancel-reason" class="form-control" name="reason" rows="2" required minlength="5" maxlength="1000">{{ old('reason') }}</textarea></div>@include('sale-returns.payment-fields', ['prefix' => 'cancel'])<button class="btn btn-outline-danger">Cancelar venta completa</button></form>
        </section>
        @endif
        @endif
    @endcan
    <script>
        (() => {
            const form = document.getElementById('returnForm');
            if (!form) return;
            const update = () => {
                let cents = 0;
                form.querySelectorAll('.return-quantity').forEach(input => {
                    const quantity = Number(input.value);
                    if (!Number.isFinite(quantity) || quantity <= 0) return;
                    const sold = Number(input.dataset.sold);
                    const next = Math.min(sold, Number(input.dataset.returned) + Math.round(quantity * 1000));
                    const cumulative = next === sold ? Number(input.dataset.total) : Math.round(Number(input.dataset.total) * (next / sold));
                    cents += cumulative - Number(input.dataset.refunded);
                });
                document.getElementById('refundEstimate').textContent = new Intl.NumberFormat('es-MX', {style: 'currency', currency: 'MXN'}).format(cents / 100);
            };
            form.addEventListener('input', update);
            update();
        })();
    </script>
</x-admin-layout>