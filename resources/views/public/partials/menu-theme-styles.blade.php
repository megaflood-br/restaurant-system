@php($palette = \App\Support\MenuTheme::palette(config('digital_menu.theme_color')))
<style>
    :root {
        --menu-400: {{ $palette['400'] }};
        --menu-500: {{ $palette['500'] }};
        --menu-600: {{ $palette['600'] }};
        --menu-700: {{ $palette['700'] }};
        --menu-100: {{ $palette['100'] }};
        --menu-200: {{ $palette['200'] }};
        --menu-800: {{ $palette['800'] }};
        --menu-gradient-to: {{ $palette['gradient_to'] }};
    }

    .menu-text { color: var(--menu-600); }
    .menu-bg { background-color: var(--menu-500); }
    .menu-bg-hover:hover { background-color: var(--menu-600); }
    .menu-bg-soft { background-color: var(--menu-100); }
    .menu-text-soft { color: var(--menu-800); }
    .menu-border-soft:hover { border-color: var(--menu-200); }
    .menu-gradient {
        background-image: linear-gradient(to bottom right, var(--menu-400), var(--menu-500), var(--menu-gradient-to));
    }
    .menu-shadow { box-shadow: 0 10px 15px -3px color-mix(in srgb, var(--menu-500) 30%, transparent); }
    .menu-focus:focus { border-color: var(--menu-500); --tw-ring-color: var(--menu-500); }
    .menu-tab-active { background-color: var(--menu-500); color: #fff; }
    .menu-icon { color: var(--menu-500); }
    .peer:checked ~ .menu-type-option {
        border-color: var(--menu-500);
        background-color: var(--menu-100);
        color: var(--menu-700);
    }
</style>
