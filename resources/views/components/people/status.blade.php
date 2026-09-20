@props(['status', 'label' => null])
@php
    $tone = match (strtolower((string) $status)) {
        'approved', 'completed', 'paid', 'repaid', 'active', 'finalized', 'closed', 'acknowledged' => 'success',
        'pending', 'issued', 'in_progress', 'waiting', 'overdue' => 'warning',
        'rejected', 'expired', 'terminated' => 'danger',
        'disbursed', 'explained', 'open' => 'info',
        default => 'neutral',
    };
@endphp
<span {{ $attributes->class(['badge', 'people-status', 'people-status--'.$tone]) }}><span class="status-dot" aria-hidden="true"></span>{{ $label ?? ucfirst(str_replace('_', ' ', $status)) }}</span>
