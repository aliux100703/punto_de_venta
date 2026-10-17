<?php

return [
    'required' => 'El campo :attribute es obligatorio.', 'string' => 'El campo :attribute debe ser texto.',
    'email' => 'El campo :attribute debe ser un correo válido.', 'numeric' => 'El campo :attribute debe ser numérico.',
    'integer' => 'El campo :attribute debe ser un entero.', 'boolean' => 'El campo :attribute debe ser verdadero o falso.',
    'exists' => 'La selección de :attribute no es válida.', 'unique' => 'El valor de :attribute ya está registrado.',
    'distinct' => 'El campo :attribute está repetido.', 'array' => 'El campo :attribute debe ser una lista.',
    'confirmed' => 'La confirmación de :attribute no coincide.', 'date' => 'El campo :attribute debe ser una fecha válida.',
    'decimal' => 'El campo :attribute debe tener entre :min y :max decimales.',
    'after_or_equal' => 'El campo :attribute debe ser una fecha igual o posterior a :date.',
    'before_or_equal' => 'El campo :attribute debe ser una fecha igual o anterior a :date.',
    'in' => 'El valor de :attribute no es válido.', 'lowercase' => 'El campo :attribute debe estar en minúsculas.',
    'min' => ['numeric' => 'El campo :attribute debe ser al menos :min.', 'string' => 'El campo :attribute debe tener al menos :min caracteres.', 'array' => 'El campo :attribute debe contener al menos :min elementos.'],
    'max' => ['numeric' => 'El campo :attribute no debe superar :max.', 'string' => 'El campo :attribute no debe superar :max caracteres.', 'array' => 'El campo :attribute no debe superar :max elementos.'],
    'gt' => ['numeric' => 'El campo :attribute debe ser mayor que :value.'],
    'attributes' => ['name' => 'nombre', 'email' => 'correo electrónico', 'password' => 'contraseña', 'amount' => 'importe', 'concept' => 'concepto', 'items' => 'productos', 'cash_received' => 'efectivo recibido', 'opening_amount' => 'fondo inicial', 'counted_cash' => 'efectivo contado', 'expiration_date' => 'fecha de vigencia', 'payment_method_id' => 'método de pago', 'unit_id' => 'unidad', 'expense_category_id' => 'categoría del gasto', 'items.*.product_id' => 'producto', 'items.*.quantity' => 'cantidad'],
];
