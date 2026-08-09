@props(['href', 'active' => false])

<a href="{{ $href }}" @class([
    'block px-4 py-2 text-sm',
    'bg-indigo-50 text-indigo-700 font-medium' => $active,
    'text-gray-700 hover:bg-gray-100' => ! $active,
])>{{ $slot }}</a>
