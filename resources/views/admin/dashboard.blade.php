<x-admin.layout title="HL Puntos — Panel">
    <h1 class="text-2xl font-semibold">Panel</h1>
    <p class="text-gray-600 mt-2">
        Hola, {{ auth('admin')->user()->display_name }} ({{ auth('admin')->user()->role }}).
    </p>
</x-admin.layout>
