<x-admin-layout title="Punto de Venta">

    <div id="posAlert"></div>

    <div class="row g-4">

        {{-- LADO IZQUIERDO --}}
        <div class="col-lg-8">

            <div class="card mb-4">
                <div class="card-body">

                    <label class="form-label fw-bold">
                        Código de barras / SKU / Producto
                    </label>

                    <div class="input-group">

                        <input
                            type="text"
                            id="productSearch"
                            class="form-control form-control-lg"
                            placeholder="Escanea o escribe el producto..."
                            autocomplete="off"
                            autofocus
                        >

                        <button
                            type="button"
                            class="btn btn-primary"
                            id="searchButton"
                        >
                            Buscar
                        </button>

                    </div>

                    <small class="text-muted">
                        Puedes utilizar directamente el lector de código de barras.
                    </small>

                </div>
            </div>


            <div class="card">
                <div class="card-body">

                    <div class="table-responsive">

                        <table class="table align-middle">

                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th style="width:130px;">Cantidad</th>
                                    <th>Precio</th>
                                    <th>IVA</th>
                                    <th>Total</th>
                                    <th style="width:60px;"></th>
                                </tr>
                            </thead>

                            <tbody id="cartBody">

                                <tr id="emptyCartRow">
                                    <td colspan="6" class="text-center text-muted py-5">
                                        No hay productos en la venta.
                                    </td>
                                </tr>

                            </tbody>

                        </table>

                    </div>

                </div>
            </div>

        </div>


        {{-- LADO DERECHO --}}
        <div class="col-lg-4">

            <div class="card position-sticky" style="top:20px;">
                <div class="card-body">

                    <h4 class="mb-4">
                        Resumen
                    </h4>


                    <div class="d-flex justify-content-between mb-2">
                        <span>Productos</span>
                        <strong id="itemsCount">
                            0
                        </strong>
                    </div>


                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal</span>
                        <strong id="subtotal">
                            $0.00
                        </strong>
                    </div>


                    <div class="d-flex justify-content-between mb-2">
                        <span>IVA</span>
                        <strong id="taxes">
                            $0.00
                        </strong>
                    </div>


                    <hr>


                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fs-4">
                            Total
                        </span>

                        <strong
                            class="fs-2"
                            id="total"
                        >
                            $0.00
                        </strong>
                    </div>


                    <hr>


                    <div class="mb-3">

                        <label class="form-label">
                            Método de pago
                        </label>

                        <select
                            id="paymentMethod"
                            class="form-select"
                        >

                            <option value="">
                                Seleccionar
                            </option>

                            @foreach($paymentMethods as $method)

                                <option value="{{ $method->id }}">
                                    {{ $method->name }}
                                </option>

                            @endforeach

                        </select>

                    </div>


                    <div
                        class="mb-3"
                        id="cashContainer"
                        style="display:none;"
                    >

                        <label class="form-label">
                            Efectivo recibido
                        </label>

                        <input
                            type="number"
                            id="cashReceived"
                            class="form-control form-control-lg"
                            min="0"
                            step="0.01"
                        >

                    </div>


                    <div
                        class="alert alert-light border mb-3"
                        id="changeContainer"
                        style="display:none;"
                    >

                        Cambio:

                        <strong
                            class="float-end"
                            id="change"
                        >
                            $0.00
                        </strong>

                    </div>


                    <button
                        type="button"
                        class="btn btn-success btn-lg w-100"
                        id="chargeButton"
                        disabled
                    >
                        Cobrar
                    </button>

                </div>
            </div>

        </div>

    </div>


    <script>

        let cart = [];


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
                        data.message ?? 'Producto no encontrado.',
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
                    quantity: 1
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
                    item.price * item.quantity;


                const tax =
                    lineSubtotal
                    * (item.tax_percentage / 100);


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
            const item = cart[index];

            let quantity =
                parseFloat(value);


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
            cart.splice(index, 1);

            renderCart();
        }


        function calculateTotals()
        {
            let subtotal = 0;
            let taxes = 0;


            cart.forEach(item => {

                const lineSubtotal =
                    item.price * item.quantity;


                const lineTax =
                    lineSubtotal
                    * (item.tax_percentage / 100);


                subtotal += lineSubtotal;

                taxes += lineTax;

            });


            return {
                subtotal,
                taxes,
                total: subtotal + taxes
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


        /*
         * Todavía no guardamos la venta.
         * Lo conectaremos en el siguiente paso.
         */
        chargeButton.addEventListener(
            'click',
            function()
            {
                const totals =
                    calculateTotals();


                console.log({
                    cart,
                    payment_method_id:
                        paymentMethod.value,
                    totals
                });


                showAlert(
                    'Carrito listo. El siguiente paso será guardar la venta.',
                    'success'
                );
            }
        );


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