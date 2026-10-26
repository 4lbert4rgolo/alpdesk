<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center px-5 py-3 bg-brand border border-transparent rounded-lg font-semibold text-sm text-white hover:bg-brand-dark focus:bg-brand-dark active:bg-brand-dark focus:outline-none focus:ring-2 focus:ring-brand-light focus:ring-offset-2 disabled:opacity-60 disabled:cursor-not-allowed transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
