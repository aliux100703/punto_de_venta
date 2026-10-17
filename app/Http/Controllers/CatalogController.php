<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\ExpenseCategory;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CatalogController extends Controller
{
    private const TYPES = ['categories' => [Category::class, 'Categorías de productos'], 'brands' => [Brand::class, 'Marcas'], 'units' => [Unit::class, 'Unidades'], 'expense-categories' => [ExpenseCategory::class, 'Categorías de gastos']];

    private function definition(string $type): array
    {
        abort_unless(isset(self::TYPES[$type]), 404);

        return self::TYPES[$type];
    }

    public function index(string $type = 'categories')
    {
        [$model, $label] = $this->definition($type);

        return view('catalogs.index', ['records' => $model::orderBy('name')->paginate(20), 'type' => $type, 'label' => $label, 'types' => self::TYPES]);
    }

    public function store(Request $request, string $type)
    {
        [$model] = $this->definition($type);
        $rules = ['name' => ['required', 'string', 'max:100', Rule::unique((new $model)->getTable(), 'name')]];
        if ($type === 'units') {
            $rules['name'] = ['required', 'string', 'max:50', Rule::unique('units', 'name')];
            $rules += ['abbreviation' => 'required|string|max:10', 'allows_decimals' => 'required|boolean'];
        }
        $model::create($request->validate($rules) + ['active' => true]);

        return back()->with('success', 'Registro creado.');
    }

    public function update(Request $request, string $type, int $id)
    {
        [$model] = $this->definition($type);
        $record = $model::findOrFail($id);
        $record->update($request->validate(['active' => 'required|boolean']));

        return back()->with('success', 'Estado actualizado.');
    }
}
