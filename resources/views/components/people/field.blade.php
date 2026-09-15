@props(['name', 'label', 'type' => 'text', 'value' => '', 'required' => true])
<label class="people-field">
    <span>{{ $label }}</span>
    <input name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $value) }}" @required($required) {{ $attributes }}>
</label>
