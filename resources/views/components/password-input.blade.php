@props(['id', 'name', 'autocomplete' => 'current-password', 'invalid' => false])

<div class="relative mt-1">
    <x-input id="{{ $id }}" name="{{ $name }}" type="password" required
             autocomplete="{{ $autocomplete }}"
             {{ $attributes->merge(['class' => 'block w-full pr-12 ' . ($invalid ? 'border-red-400' : '')]) }} />

    <button type="button" data-toggle-password="{{ $id }}" aria-label="Mostrar senha" aria-pressed="false"
            class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-500 hover:text-brand focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-light rounded-r-lg">
        <svg data-eye-open class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>
        </svg>
        <svg data-eye-closed class="hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M17.9 17.9A10.4 10.4 0 0112 19c-6.4 0-10-7-10-7a18.5 18.5 0 015.1-5.9M9.9 5.2A9.8 9.8 0 0112 5c6.4 0 10 7 10 7a18.6 18.6 0 01-2.2 3.2M1 1l22 22"/><path d="M14.1 14.1a3 3 0 11-4.2-4.2"/>
        </svg>
    </button>
</div>
