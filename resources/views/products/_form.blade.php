<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">Nombre *</label>

        <input
            type="text"
            name="name"
            class="form-control"
            value="{{ old('name', $product->name ?? '') }}"
            required
        >

        @error('name')
            <div class="text-danger">
                {{ $message }}
            </div>
        @enderror
    </div>

    <div class="col-md-3 mb-3">
        <label class="form-label">SKU</label>

        <input
            type="text"
            name="sku"
            class="form-control"
            value="{{ old('sku', $product->sku ?? '') }}"
        >
    </div>

    <div class="col-md-3 mb-3">
        <label class="form-label">Código de barras</label>

        <input
            type="text"
            name="barcode"
            class="form-control"
            value="{{ old('barcode', $product->barcode ?? '') }}"
        >
    </div>
</div>

<div class="row">
    <div class="col-md-4 mb-3">
        <label class="form-label">Categoría</label>

        <select
            name="category_id"
            class="form-select"
        >
            <option value="">Seleccionar</option>

            @foreach($categories as $category)
                <option
                    value="{{ $category->id }}"
                    @selected(
                        old(
                            'category_id',
                            $product->category_id ?? null
                        ) == $category->id
                    )
                >
                    {{ $category->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-md-4 mb-3">
        <label class="form-label">Marca</label>

        <select
            name="brand_id"
            class="form-select"
        >
            <option value="">Sin marca</option>

            @foreach($brands as $brand)
                <option
                    value="{{ $brand->id }}"
                    @selected(
                        old(
                            'brand_id',
                            $product->brand_id ?? null
                        ) == $brand->id
                    )
                >
                    {{ $brand->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-md-4 mb-3">
        <label class="form-label">Unidad *</label>

        <select
            name="unit_id"
            class="form-select"
            required
        >
            <option value="">Seleccionar</option>

            @foreach($units as $unit)
                <option
                    value="{{ $unit->id }}"
                    @selected(
                        old(
                            'unit_id',
                            $product->unit_id ?? null
                        ) == $unit->id
                    )
                >
                    {{ $unit->name }}
                    ({{ $unit->abbreviation }})
                </option>
            @endforeach
        </select>
    </div>
</div>

<div class="row">
    <div class="col-md-3 mb-3">
        <label class="form-label">Costo *</label>

        <input
            type="number"
            step="0.01"
            min="0"
            name="cost"
            class="form-control"
            value="{{ old('cost', $product->cost ?? 0) }}"
            required
        >
    </div>

    <div class="col-md-3 mb-3">
        <label class="form-label">Precio venta *</label>

        <input
            type="number"
            step="0.01"
            min="0"
            name="sale_price"
            class="form-control"
            value="{{ old('sale_price', $product->sale_price ?? 0) }}"
            required
        >
    </div>

    <div class="col-md-3 mb-3">
        <label class="form-label">IVA %</label>

        <input
            type="number"
            step="0.01"
            min="0"
            max="100"
            name="tax_percentage"
            class="form-control"
            value="{{ old(
                'tax_percentage',
                $product->tax_percentage ?? 16
            ) }}"
            required
        >
    </div>

    <div class="col-md-3 mb-3">
        <label class="form-label">Stock mínimo *</label>

        <input
            type="number"
            step="0.001"
            min="0"
            name="minimum_stock"
            class="form-control"
            value="{{ old(
                'minimum_stock',
                $product->minimum_stock ?? 0
            ) }}"
            required
        >
    </div>
</div>

<div class="mb-3">
    <label class="form-label">Descripción</label>

    <textarea
        name="description"
        class="form-control"
        rows="3"
    >{{ old(
        'description',
        $product->description ?? ''
    ) }}</textarea>
</div>

@if(isset($product))
    <div class="mb-3">
        <label class="form-label">Estado</label>

        <select
            name="active"
            class="form-select"
        >
            <option
                value="1"
                @selected(old('active', $product->active) == 1)
            >
                Activo
            </option>

            <option
                value="0"
                @selected(old('active', $product->active) == 0)
            >
                Inactivo
            </option>
        </select>
    </div>
@endif