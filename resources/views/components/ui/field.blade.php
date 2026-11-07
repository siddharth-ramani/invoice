@props(['name', 'label', 'type' => 'text', 'value' => '', 'help' => null, 'required' => false, 'recover' => true])
@php
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $id = $attributes->get('id', str_replace(['.', '_'], '-', $key));
    $id = str_replace('--INDEX--', '__INDEX__', $id);
    $previous = $recover ? old($key, $value) : $value;
    $safeValue = is_scalar($previous) ? $previous : '';
    $described = trim(($help ? $id.'-help ' : '').($errors->has($key) ? $id.'-error' : ''));
@endphp
<div class="app-field">
    <label for="{{ $id }}" class="form-label">{{ $label }}@if($required)<span class="required-mark" aria-hidden="true"> *</span>@endif</label>
    @if($type === 'textarea')
    <textarea name="{{ $name }}" id="{{ $id }}" {{ $attributes->except('id')->class(['form-control', 'is-invalid' => $errors->has($key)]) }} @required($required) @if($described) aria-describedby="{{ $described }}" @endif @if($errors->has($key)) aria-invalid="true" @endif>{{ $safeValue }}</textarea>
    @else
    <input type="{{ $type }}" name="{{ $name }}" id="{{ $id }}" value="{{ $type === 'password' ? '' : $safeValue }}" {{ $attributes->except('id')->class(['form-control', 'is-invalid' => $errors->has($key)]) }} @required($required) @if($described) aria-describedby="{{ $described }}" @endif @if($errors->has($key)) aria-invalid="true" @endif>
    @endif
    @if($help)<p class="form-text" id="{{ $id }}-help">{{ $help }}</p>@endif
    @error($key)<p class="invalid-feedback" id="{{ $id }}-error">{{ __($message) }}</p>@enderror
</div>
