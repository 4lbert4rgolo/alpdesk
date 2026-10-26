@props(['disabled' => false])

<input {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'border-gray-300 focus:border-brand-light focus:ring-brand-light rounded-lg shadow-sm']) !!}>
