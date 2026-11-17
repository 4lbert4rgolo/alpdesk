<x-guest-layout>
    <div class="min-h-screen bg-gray-50 px-4 py-10">
        <div class="mx-auto flex max-w-3xl flex-col items-center">
            <x-authentication-card-logo />

            <article class="prose prose-headings:text-graphite prose-a:text-brand mt-8 w-full max-w-none rounded-2xl bg-white p-6 shadow-md sm:p-10">
                {!! $terms !!}
            </article>
        </div>
    </div>
</x-guest-layout>
