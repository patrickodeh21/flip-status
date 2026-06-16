@props([
    'variant' => 'primary',
    'iconOnly' => false,
    'srText' => '',
    'href' => false,
    'size' => 'base',
    'disabled' => false,
    'pill' => false,
    'squared' => false,
])

@php
    use App\Models\Setting;

    // Helper function to darken a hex color (only declare if not already exists)
    if (!function_exists('darkenColor')) {
        function darkenColor($hex, $percent = 15) {
            $hex = str_replace('#', '', $hex);
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));

            $r = max(0, min(255, $r - ($r * $percent / 100)));
            $g = max(0, min(255, $g - ($g * $percent / 100)));
            $b = max(0, min(255, $b - ($b * $percent / 100)));

            return '#' . str_pad(dechex($r), 2, '0', STR_PAD_LEFT) .
                       str_pad(dechex($g), 2, '0', STR_PAD_LEFT) .
                       str_pad(dechex($b), 2, '0', STR_PAD_LEFT);
        }
    }

    $baseClasses =
        'inline-flex !py-1 items-center transition-colors font-medium select-none disabled:opacity-50 disabled:cursor-not-allowed focus:outline-none focus:ring focus:ring-offset-2 focus:ring-offset-white dark:focus:ring-offset-dark-eval-2';

    $variantClasses = '';
    $inlineStyles = '';
    $focusRingColor = '';

    switch ($variant) {
        case 'primary':
            $variantClasses = 'text-white';
            $inlineStyles = "background-color: var(--button-primary-color);";
            $focusRingColor = 'button-primary';
            break;
        case 'secondary':
            $variantClasses =
                'bg-white text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:bg-dark-eval-1 dark:hover:bg-dark-eval-2 dark:hover:text-gray-200 border border-gray-300 dark:border-gray-700';
            $focusRingColor = 'button-primary';
            break;
        case 'success':
            $successColor = Setting::get('button_success_color', '#10b981');
            $hoverColor = darkenColor($successColor, 15);
            $variantClasses = 'text-white';
            $inlineStyles = "background-color: {$successColor};";
            $focusRingColor = $successColor;
            break;
        case 'danger':
            $dangerColor = Setting::get('button_danger_color', '#ef4444');
            $hoverColor = darkenColor($dangerColor, 15);
            $variantClasses = 'text-white';
            $inlineStyles = "background-color: {$dangerColor};";
            $focusRingColor = $dangerColor;
            break;
        case 'warning':
            $warningColor = Setting::get('button_warning_color', '#f59e0b');
            $hoverColor = darkenColor($warningColor, 15);
            $variantClasses = 'text-white';
            $inlineStyles = "background-color: {$warningColor};";
            $focusRingColor = $warningColor;
            break;
        case 'info':
            $infoColor = Setting::get('button_info_color', '#06b6d4');
            $hoverColor = darkenColor($infoColor, 15);
            $variantClasses = 'text-white';
            $inlineStyles = "background-color: {$infoColor};";
            $focusRingColor = $infoColor;
            break;
        case 'black':
            $variantClasses =
                'bg-black text-gray-300 hover:text-white hover:bg-gray-800 focus:ring-black dark:hover:bg-dark-eval-3';
            $focusRingColor = '#000000';
            break;
        default:
            $variantClasses = 'text-white';
            $inlineStyles = "background-color: var(--button-primary-color);";
            $focusRingColor = 'button-primary';
    }

    switch ($size) {
        case 'sm':
            $sizeClasses = $iconOnly ? 'p-1.5' : 'px-2.5 py-1.5 text-sm';
            break;
        case 'base':
            $sizeClasses = $iconOnly ? 'p-2' : 'px-4 py-2 text-base';
            break;
        case 'lg':
        default:
            $sizeClasses = $iconOnly ? 'p-3' : 'px-5 py-2 text-xl';
            break;
    }

    $classes = $baseClasses . ' ' . $sizeClasses . ' ' . $variantClasses;

    if (!$squared && !$pill) {
        $classes .= ' rounded-md';
    } elseif ($pill) {
        $classes .= ' rounded-full';
    }

    // Add focus ring color class if needed
    if ($focusRingColor && in_array($variant, ['primary', 'secondary', 'success', 'danger', 'warning', 'info'])) {
        $classes .= ' focus:ring';
    }

@endphp

@php
    // Build style attribute
    $styleAttr = '';
    if ($inlineStyles) {
        $styleAttr = "style=\"{$inlineStyles}\"";
    }

    // Add focus ring style
    $focusStyle = '';
    if ($focusRingColor && in_array($variant, ['primary', 'secondary', 'success', 'danger', 'warning', 'info'])) {
        $focusStyle = "data-focus-ring=\"{$focusRingColor}\"";
    }
@endphp

@if ($href)
    <a href="{{ $href }}"
       {{ $attributes->merge(['class' => $classes]) }}
       {!! $styleAttr !!}
       {!! $focusStyle !!}>
        {{ $slot }}
        @if ($iconOnly)
            <span class="sr-only">{{ $srText ?? '' }}</span>
        @endif
    </a>
@else
    <button {{ $attributes->merge(['type' => 'submit', 'class' => $classes]) }}
            {!! $styleAttr !!}
            {!! $focusStyle !!}
            @if($disabled) disabled @endif>
        {{ $slot }}
        @if ($iconOnly)
            <span class="sr-only">{{ $srText ?? '' }}</span>
        @endif
    </button>
@endif

@if($focusRingColor && in_array($variant, ['primary', 'secondary', 'success', 'danger', 'warning', 'info']))
    <style>
        @if($focusRingColor === 'button-primary')
            [data-focus-ring="button-primary"]:focus {
                --tw-ring-color: var(--button-primary-color) !important;
            }
            [style*="background-color: var(--button-primary-color)"]:hover {
                background-color: color-mix(in srgb, var(--button-primary-color) 85%, black) !important;
            }
        @else
            [data-focus-ring="{{ $focusRingColor }}"]:focus {
                --tw-ring-color: {{ $focusRingColor }} !important;
            }
            @if(isset($hoverColor))
                [style*="--btn-hover-color"]:hover {
                    background-color: var(--btn-hover-color) !important;
                }
            @endif
        @endif
    </style>
@endif
