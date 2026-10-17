<x-admin-layout title="Nuevo producto">

    <x-slot name="header">
        <h2 class="font-semibold text-xl">
            Nuevo producto
        </h2>
    </x-slot>

    <div class="py-4">
        <div class="container">

            <div class="card">
                <div class="card-body">

                    <form
                        method="POST"
                        action="{{ route('products.store') }}"
                    >
                        @csrf

                        @include('products._form')

                        <div class="d-flex gap-2">
                            <button
                                type="submit"
                                class="btn btn-primary"
                            >
                                Guardar producto
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

</x-admin-layout>