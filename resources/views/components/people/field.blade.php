@props(['name', 'label', 'type' => 'text', 'value' => '', 'required' => true, 'hint' => null])
@php
    $fieldId = $attributes->get('id') ?: 'field-'.str_replace(['[', ']'], ['-', ''], $name).'-'.\Illuminate\Support\Str::random(8);
    $invalid = $errors->has($name);
    $description = trim(($hint ? $fieldId.'-hint ' : '').($invalid ? $fieldId.'-error' : ''));
@endphp
<label class="people-field">
    <span>{{ $label }}</span>
    <input id="{{ $fieldId }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $value) }}" @required($required)
           @if($invalid) aria-invalid="true" @endif @if($description) aria-describedby="{{ $description }}" @endif {{ $attributes->except('id') }}>
    @if($hint)<small id="{{ $fieldId }}-hint" class="field-hint">{{ $hint }}</small>@endif
    @error($name)<small id="{{ $fieldId }}-error" class="field-error" data-field-error="{{ $name }}">{{ $message }}</small>@enderror
</label>
