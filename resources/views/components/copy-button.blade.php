@props(['value'])
<button
    type="button"
    {{ $attributes->merge(['class' => 'cc-btn cc-btn--secondary cc-btn--sm']) }}
    x-data="copyText(@js($value))"
    @click="copy"
    :aria-label="label"
>
    <x-icon name="copy" />
    <span
        x-text="label"
        aria-live="polite"
    >Copy</span>
</button>
