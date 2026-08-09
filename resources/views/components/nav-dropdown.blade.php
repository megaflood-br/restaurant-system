@props(['label', 'active' => false, 'align' => 'left'])

@php
$triggerClasses = $active
    ? 'inline-flex items-center px-1 pt-1 border-b-2 border-indigo-400 text-sm font-medium leading-5 text-gray-900 focus:outline-none focus:border-indigo-700 transition duration-150 ease-in-out'
    : 'inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300 transition duration-150 ease-in-out';
@endphp

<div class="relative inline-flex items-center" x-data="{ open: false }" @click.outside="open = false">
    <button type="button" @click="open = !open" class="{{ $triggerClasses }} gap-1">
        {{ $label }}
        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
    </button>
    <div x-show="open" x-cloak
        class="absolute top-full {{ $align === 'left' ? 'left-0' : 'right-0' }} mt-1 w-52 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50 py-1">
        {{ $slot }}
    </div>
</div>
