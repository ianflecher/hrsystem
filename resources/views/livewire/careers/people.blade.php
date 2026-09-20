<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

/*
 * The people. No quotations: the three that used to sit here were invented, and
 * they sat beneath photographs of real, identifiable staff, which reads as
 * words put in their mouths. Photographs until somebody has agreed to be quoted.
 */
new #[Layout('components.layouts.careers')] class extends Component {}; ?>

<div>
    @include('partials.careers-team-strip')

    @include('partials.careers-cta')
</div>
