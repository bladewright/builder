@props(['href', 'tip'])

{{-- The round buttons beside a heading. **The explanation is a tooltip**,
     so nobody presses an icon without knowing what it does. --}}
<a href="{{ $href }}" data-tip="{{ $tip }}" aria-label="{{ $tip }}"
   {{ $attributes->merge(['class' => 'bw-tip inline-flex cursor-pointer items-center justify-center rounded-lg border border-gray-300 bg-white p-2 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-gray-100']) }}>
    {{ $slot }}
</a>
