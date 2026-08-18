<x-app-layout>

    <x-slot name="header">
        <h2 class="font-semibold text-xl">
            Editar producto
        </h2>
    </x-slot>

    <div class="py-4">
        <div class="container">

            <div class="card">
                <div class="card-body">

                    <form
                        method="POST"
                        action="{{ route(
                            'products.update',
                            $product
                        ) }}"
                    >
                        @csrf
                        @method('PUT')

                        @include('products._form')

                        <div class="d-flex gap-2">

                            <button
                                type="submit"
                                class="btn btn-primary"
                            >
                                Guardar cambios
                            </button>

                            <a
                                href="{{ route('products.index') }}"
                                class="btn btn-secondary"
                            >
                                Cancelar
                            </a>

                        </div>

                    </form>

                </div>
            </div>

        </div>
    </div>

</x-app-layout>