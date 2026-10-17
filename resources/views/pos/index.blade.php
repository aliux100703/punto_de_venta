<x-admin-layout title="Punto de Venta">
    <div class="page-heading dashboard-heading"><div><span class="eyebrow">CADA VENTA CUENTA</span><h1>Listo para vender.</h1><p>Encuentra un producto, agrégalo a la venta y atiende a tu próximo cliente.</p></div><span class="date-pill"><x-icon name="scan" size="16" /> Lector de códigos compatible</span></div>
    <div id="posAlert" aria-live="polite"></div>
    <a id="saleReceipt" class="btn btn-outline-primary mb-3" hidden>Ver e imprimir última venta</a>
    <div class="pos-workspace">
        <div class="pos-workspace-left">
            <section class="card pos-search-panel"><div class="card-body">
                <label class="form-label" for="productSearch">¿Qué producto está buscando tu cliente?</label>
                <div class="pos-search-row"><div class="search-field"><x-icon name="scan" size="20" /><input type="text" id="productSearch" class="form-control" placeholder="Escanea el código o escribe un nombre / SKU…" autocomplete="off" autofocus></div><button type="button" class="btn btn-primary" id="searchButton"><x-icon name="search" size="17" /> Buscar</button></div>
                <div class="search-hint"><x-icon name="check" size="11" /> Escanea directamente con tu lector o presiona Enter para buscar.</div>
                <div class="pos-customer-row"><x-icon name="users" size="19" /><label for="customerId">Cliente de la venta</label><select id="customerId" class="form-select"><option value="">Público general</option>@foreach($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->name }}</option>@endforeach</select></div>
            </div></section>
            <section class="card pos-products-panel"><div class="panel-heading"><div><h2>Al alcance de un clic</h2><p>Productos disponibles · Precios con IVA</p></div><a href="{{ route('products.index') }}">Ver catálogo <x-icon name="arrow" size="14" /></a></div>
                @if($quickProducts->isNotEmpty())
                <div class="quick-products">@foreach($quickProducts as $product)<button type="button" class="quick-product" onclick="addProduct(@js($product))" aria-label="Agregar {{ $product['name'] }}"><span class="quick-product-icon"><x-icon name="box" size="21" /><x-icon name="plus" size="13" /></span><strong>{{ $product['name'] }}</strong><small>{{ number_format($product['stock'], $product['allows_decimals'] ? 2 : 0) }} {{ $product['unit'] }} disponibles</small><span class="quick-product-price">${{ number_format($product['price'] * (1 + $product['tax_percentage'] / 100), 2) }}</span></button>@endforeach</div>
                @else<div class="empty-state"><x-icon name="box" size="25" /><strong>Prepara tu catálogo para vender.</strong><p>Los productos activos con existencias aparecerán aquí.</p></div>@endif
            </section>
            <section class="card"><div class="panel-heading"><div class="cart-header"><x-icon name="cart" size="19" /><h2>Tu venta actual</h2></div><button type="button" class="clear-cart-button" onclick="clearCart()"><x-icon name="trash" size="13" /> Vaciar venta</button></div>
                <div class="table-responsive"><table class="table align-middle pos-cart-table"><thead><tr><th>Producto</th><th>Cantidad</th><th>Precio</th><th>IVA</th><th>Total</th><th><span class="visually-hidden">Quitar</span></th></tr></thead><tbody id="cartBody"><tr id="emptyCartRow"><td colspan="6"><div class="empty-state"><x-icon name="cart" size="27" /><strong>Una nueva venta, una nueva oportunidad.</strong><p>Busca o selecciona un producto para comenzar.</p></div></td></tr></tbody></table></div>
            </section>
        </div>
        <section class="card checkout-card"><div class="checkout-top"><x-icon name="receipt" size="22" /><div><h2>Resumen de la venta</h2><small>Todo claro antes de cobrar</small></div></div><div class="card-body">
            <div class="checkout-line"><span>Productos en la venta</span><strong id="itemsCount">0</strong></div><div class="checkout-line"><span>Subtotal</span><strong id="subtotal">$0.00</strong></div><div class="checkout-line"><span>IVA</span><strong id="taxes">$0.00</strong></div>
            <div class="checkout-total"><span>Total a cobrar <strong id="total">$0.00</strong></span><small>Pesos mexicanos · MXN</small></div>
            <div class="mb-3"><label class="form-label" for="paymentMethod">Método de pago</label><select id="paymentMethod" class="form-select"><option value="">Selecciona un método</option>@foreach($paymentMethods as $method)<option value="{{ $method->id }}">{{ $method->name }}</option>@endforeach</select></div>
            <div class="mb-3" id="cashContainer" style="display:none"><label class="form-label" for="cashReceived">Efectivo recibido</label><input type="number" id="cashReceived" class="form-control form-control-lg" min="0" step="0.01" placeholder="$0.00"></div>
            <div class="alert alert-success mb-3" id="changeContainer" style="display:none">Cambio a entregar <strong class="float-end" id="change">$0.00</strong></div>
            <button type="button" class="btn btn-success btn-lg w-100" id="chargeButton" disabled>Cobrar</button>
            <div class="checkout-trust"><x-icon name="shield" size="12" /> Tu venta se registra de forma segura</div>
            <div class="pos-cash-notice"><x-icon name="wallet" size="17" /><span>{{ $activeCashRegister ? 'Tu caja está abierta y lista para recibir pagos en efectivo.' : 'Para recibir pagos en efectivo, primero abre tu caja.' }} <a href="{{ route('cash.index') }}">{{ $activeCashRegister ? 'Ver mi caja' : 'Abrir mi caja' }} <span aria-hidden="true">→</span></a></span></div>
        </div></section>
    </div>
    <script>

        let cart = [];
        let processingSale = false;


        const searchInput =
            document.getElementById('productSearch');

        const searchButton =
            document.getElementById('searchButton');

        const cartBody =
            document.getElementById('cartBody');

        const paymentMethod =
            document.getElementById('paymentMethod');

        const cashContainer =
            document.getElementById('cashContainer');

        const cashReceived =
            document.getElementById('cashReceived');

        const changeContainer =
            document.getElementById('changeContainer');

        const chargeButton =
            document.getElementById('chargeButton');


        /*
         * Buscar al presionar ENTER.
         * Esto hace que funcione muy bien con lectores USB.
         */
        searchInput.addEventListener('keydown', function(event) {

            if (event.key === 'Enter') {

                event.preventDefault();

                searchProduct();

            }

        });


        searchButton.addEventListener('click', function() {
            searchProduct();
        });


        async function searchProduct()
        {
            if (processingSale) return;
            const search = searchInput.value.trim();

            if (!search) {
                return;
            }

            try {

                const response = await fetch(
                    "{{ route('pos.search-product') }}"
                    + '?search='
                    + encodeURIComponent(search),
                    {
                        headers: {
                            'Accept': 'application/json'
                        }
                    }
                );


                const data = await response.json();


                if (!response.ok) {

                    showAlert(
                        (data.message ?? 'Producto no encontrado.') + (data.request_id && !(data.message ?? '').includes(data.request_id) ? ' Referencia: ' + data.request_id : ''),
                        'danger'
                    );

                    searchInput.select();

                    return;
                }


                addProduct(data.product);

                searchInput.value = '';
                searchInput.focus();

            } catch (error) {

                console.error(error);

                showAlert(
                    'Ocurrió un error al buscar el producto.',
                    'danger'
                );

            }
        }


        function addProduct(product)
        {
            if (processingSale) return;
            if (product.stock <= 0) {

                showAlert(
                    'El producto no tiene existencia disponible.',
                    'warning'
                );

                return;
            }


            const existing =
                cart.find(item => item.id === product.id);


            if (existing) {

                const newQuantity =
                    existing.quantity + 1;


                if (newQuantity > product.stock) {

                    showAlert(
                        'No hay suficiente inventario.',
                        'warning'
                    );

                    return;
                }


                existing.quantity =
                    newQuantity;

            } else {

                cart.push({
                    ...product,
                    quantity: product.allows_decimals ? Math.min(1, product.stock) : 1
                });

            }


            renderCart();
        }


        function renderCart()
        {
            cartBody.innerHTML = '';


            if (cart.length === 0) {

                cartBody.innerHTML = `
                    <tr>
                        <td
                            colspan="6"
                            class="text-center text-muted py-5"
                        >
                            No hay productos en la venta.
                        </td>
                    </tr>
                `;

                updateTotals();

                return;
            }


            cart.forEach((item, index) => {

                const row =
                    document.createElement('tr');


                const lineSubtotal =
                    Math.round((item.price * item.quantity + Number.EPSILON) * 100) / 100;


                const tax = Math.round((lineSubtotal * item.tax_percentage / 100 + Number.EPSILON) * 100) / 100;


                const lineTotal =
                    lineSubtotal + tax;


                row.innerHTML = `

                    <td>

                        <strong>
                            ${escapeHtml(item.name)}
                        </strong>

                        <div class="small text-muted">
                            ${escapeHtml(
                                item.barcode
                                ?? item.sku
                                ?? ''
                            )}
                        </div>

                        <div class="small text-muted">
                            Stock:
                            ${formatQuantity(item.stock)}
                            ${escapeHtml(item.unit ?? '')}
                        </div>

                    </td>


                    <td>

                        <input
                            type="number"
                            class="form-control"
                            min="${item.allows_decimals ? '0.001' : '1'}"
                            step="${item.allows_decimals ? '0.001' : '1'}"
                            max="${item.stock}"
                            value="${item.quantity}"
                            onchange="changeQuantity(
                                ${index},
                                this.value
                            )"
                        >

                    </td>


                    <td>
                        ${money(item.price)}
                    </td>


                    <td>
                        ${money(tax)}
                    </td>


                    <td>
                        <strong>
                            ${money(lineTotal)}
                        </strong>
                    </td>


                    <td>

                        <button
                            type="button"
                            class="btn btn-sm btn-outline-danger"
                            onclick="removeProduct(${index})"
                        >
                            ×
                        </button>

                    </td>
                `;


                cartBody.appendChild(row);
            });


            updateTotals();
        }


        function changeQuantity(index, value)
        {
            if (processingSale) { renderCart(); return; }
            const item = cart[index];

            let quantity =
                Math.round(parseFloat(value) * 1000) / 1000;


            if (
                Number.isNaN(quantity)
                ||
                quantity <= 0
            ) {

                renderCart();

                return;
            }


            if (!item.allows_decimals) {

                quantity =
                    Math.floor(quantity);

            }


            if (quantity > item.stock) {

                showAlert(
                    'La cantidad supera el inventario disponible.',
                    'warning'
                );

                renderCart();

                return;
            }


            item.quantity =
                quantity;


            renderCart();
        }


        function removeProduct(index)
        {
            if (processingSale) return;
            cart.splice(index, 1);

            renderCart();
        }


        function calculateTotals()
        {
            let subtotal = 0;
            let taxes = 0;


            cart.forEach(item => {

                const lineSubtotal =
                    Math.round((item.price * item.quantity + Number.EPSILON) * 100) / 100;


                const lineTax =
                    lineSubtotal
                    * (item.tax_percentage / 100);


                subtotal += lineSubtotal;

                taxes += Math.round((lineTax + Number.EPSILON) * 100) / 100;

            });


            return {
                subtotal,
                taxes,
                total: Math.round((subtotal + taxes + Number.EPSILON) * 100) / 100
            };
        }


        function updateTotals()
        {
            const totals =
                calculateTotals();


            document.getElementById(
                'itemsCount'
            ).textContent =
                cart.length;


            document.getElementById(
                'subtotal'
            ).textContent =
                money(totals.subtotal);


            document.getElementById(
                'taxes'
            ).textContent =
                money(totals.taxes);


            document.getElementById(
                'total'
            ).textContent =
                money(totals.total);


            updateChange();

            validateChargeButton();
        }


        paymentMethod.addEventListener(
            'change',
            function()
            {
                const selectedText =
                    this.options[
                        this.selectedIndex
                    ]?.text
                    ?.trim()
                    ?.toLowerCase();


                if (selectedText === 'efectivo') {

                    cashContainer.style.display =
                        'block';

                    changeContainer.style.display =
                        'block';

                    setTimeout(() => {
                        cashReceived.focus();
                    }, 50);

                } else {

                    cashContainer.style.display =
                        'none';

                    changeContainer.style.display =
                        'none';

                    cashReceived.value = '';

                }


                validateChargeButton();
            }
        );


        cashReceived.addEventListener(
            'input',
            function()
            {
                updateChange();

                validateChargeButton();
            }
        );


        function updateChange()
        {
            const totals =
                calculateTotals();


            const received =
                parseFloat(
                    cashReceived.value
                ) || 0;


            const change =
                received - totals.total;


            document.getElementById(
                'change'
            ).textContent =
                money(
                    change > 0
                    ? change
                    : 0
                );
        }


        function validateChargeButton()
        {
            if (processingSale) { chargeButton.disabled = true; return; }
            const totals =
                calculateTotals();


            if (
                cart.length === 0
                ||
                !paymentMethod.value
            ) {

                chargeButton.disabled =
                    true;

                return;
            }


            const selectedText =
                paymentMethod.options[
                    paymentMethod.selectedIndex
                ]?.text
                ?.trim()
                ?.toLowerCase();


            if (selectedText === 'efectivo') {

                const received =
                    parseFloat(
                        cashReceived.value
                    ) || 0;


                chargeButton.disabled =
                    received < totals.total;

                return;
            }


            chargeButton.disabled =
                false;
        }



       chargeButton.addEventListener(
    'click',
    async function()
    {
        if (cart.length === 0) {
            return;
        }

        if (!paymentMethod.value) {
            showAlert(
                'Selecciona un método de pago.',
                'warning'
            );

            return;
        }

        /*
         * Evitar doble clic mientras se procesa la venta.
         */
        processingSale = true;
        chargeButton.disabled = true;
        chargeButton.textContent = 'Procesando...';


        const payload = {

            customer_id: document.getElementById('customerId').value || null,
            payment_method_id:
                paymentMethod.value,

            cash_received:
                cashReceived.value
                    ? parseFloat(cashReceived.value)
                    : null,

            /*
             * IMPORTANTE:
             * No enviamos precios ni totales.
             *
             * El servidor volverá a consultar los precios
             * directamente desde la base de datos.
             */
            items: cart.map(item => ({
                product_id: item.id,
                quantity: item.quantity
            }))
        };


        try {

            const response = await fetch(
                "{{ route('pos.sale.store') }}",
                {
                    method: 'POST',

                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',

                        'X-CSRF-TOKEN':
                            "{{ csrf_token() }}"
                    },

                    body: JSON.stringify(payload)
                }
            );


            const data = await response.json();


            /*
             * Error de validación o del servidor.
             */
            if (!response.ok) {

                let message =
                    data.message
                    ?? 'No fue posible realizar la venta.';


                if (data.errors) {

                    message =
                        Object.values(data.errors)
                            .flat()
                            .join(' ');
                }


                showAlert(
                    message + (data.request_id && !message.includes(data.request_id) ? ' Referencia: ' + data.request_id : ''),
                    'danger'
                );

                return;
            }


            /*
             * Venta guardada correctamente.
             */
            let message =
                'Venta '
                + data.sale.folio
                + ' realizada correctamente. Total: '
                + money(data.sale.total) + '. Cambio: ' + money(data.sale.change);


            showAlert(
                message,
                'success'
            );





            /*
             * Limpiar carrito.
             */
            cart = [];
            document.getElementById('customerId').value = '';
            const receipt = document.getElementById('saleReceipt');
            receipt.href = data.sale.url;
            receipt.hidden = false;


            /*
             * Limpiar método de pago.
             */
            paymentMethod.value = '';


            /*
             * Limpiar efectivo.
             */
            cashReceived.value = '';


            /*
             * Ocultar campos de efectivo.
             */
            cashContainer.style.display =
                'none';

            changeContainer.style.display =
                'none';


            /*
             * Redibujar carrito.
             */
            renderCart();


            /*
             * Devolver foco al lector.
             */
            searchInput.value = '';
            searchInput.focus();


        } catch (error) {

            console.error(error);

            showAlert(
                'Ocurrió un error al procesar la venta.',
                'danger'
            );

        } finally {
            processingSale = false;

            chargeButton.textContent =
                'Cobrar';

            validateChargeButton();
        }
    }
);


        function clearCart() {
            if (processingSale || cart.length === 0) return;
            if (!confirm('¿Quieres quitar todos los productos de esta venta?')) return;
            cart = [];
            renderCart();
            searchInput.focus();
        }
        function money(value)
        {
            return new Intl.NumberFormat(
                'es-MX',
                {
                    style: 'currency',
                    currency: 'MXN'
                }
            ).format(value);
        }


        function formatQuantity(value)
        {
            return Number(value)
                .toLocaleString(
                    'es-MX',
                    {
                        maximumFractionDigits: 3
                    }
                );
        }


        function showAlert(message, type)
        {
            const container =
                document.getElementById(
                    'posAlert'
                );


            container.innerHTML = `

                <div
                    class="alert alert-${type} alert-dismissible fade show"
                    role="alert"
                >

                    ${escapeHtml(message)}

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                    ></button>

                </div>
            `;
        }


        function escapeHtml(value)
        {
            const div =
                document.createElement('div');

            div.textContent =
                value ?? '';

            return div.innerHTML;
        }

    </script>

</x-admin-layout>