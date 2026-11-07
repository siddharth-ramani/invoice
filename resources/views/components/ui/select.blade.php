@props(['name', 'label', 'required' => false, 'help' => null])
@php $id = $attributes->get('id', str_replace('_', '-', $name)); @endphp
<div class="app-field">
    <label class="form-label" for="{{ $id }}">{{ $label }}@if($required)<span class="required-mark" aria-hidden="true"> *</span>@endif</label>
    <select name="{{ $name }}" id="{{ $id }}" {{ $attributes->except('id')->class(['form-select', 'is-invalid' => $errors->has($name)]) }} @required($required) @if($errors->has($name)) aria-invalid="true" aria-describedby="{{ $id }}-error" @elseif($help) aria-describedby="{{ $id }}-help" @endif>{{ $slot }}</select>
    @if($help)<p class="form-text" id="{{ $id }}-help">{{ $help }}</p>@endif
    @error($name)<p class="invalid-feedback" id="{{ $id }}-error">{{ __($message) }}</p>@enderror
</div>
