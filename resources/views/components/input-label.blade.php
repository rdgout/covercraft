@props(['value'])
<label {{ $attributes->merge(['class' => 'cc-label']) }}>{{ $value ?? $slot }}</label>
