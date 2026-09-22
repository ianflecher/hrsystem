{{-- Government numbers and who to call if something happens - split from
     Personal details onto its own step so that step is not the longest one. --}}
<section class="bg-white rounded-xl border border-gray-200 shadow-sm mb-6">
    <div class="px-6 py-4 border-b border-gray-200">
        <h2 class="font-semibold text-gray-900">Government numbers &amp; emergency contact</h2>
    </div>

    {{-- ------------------------------------------------------ government IDs --}}
    <div class="px-6 py-4">
        <h3 class="font-medium text-gray-900 mb-3">Government numbers</h3>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="form-label" for="sss_number">SSS no.</label>
                <input id="sss_number" type="text" wire:model="p.sss_number" class="form-input" placeholder="N/A if none">
            </div>
            <div>
                <label class="form-label" for="pagibig_number">Pag-IBIG no.</label>
                <input id="pagibig_number" type="text" wire:model="p.pagibig_number" class="form-input" placeholder="N/A if none">
            </div>
            <div>
                <label class="form-label" for="philhealth_number">PhilHealth no.</label>
                <input id="philhealth_number" type="text" wire:model="p.philhealth_number" class="form-input" placeholder="N/A if none">
            </div>
            <div>
                <label class="form-label" for="tin">TIN</label>
                <input id="tin" type="text" wire:model="p.tin" class="form-input" placeholder="N/A if none">
            </div>
        </div>
    </div>

    {{-- ---------------------------------------------------- emergency contact --}}
    <div class="px-6 py-4 border-t border-gray-200">
        <h3 class="font-medium text-gray-900">In case of emergency, please contact</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
            <div>
                <label class="form-label" for="emergency_name">Name</label>
                <input id="emergency_name" type="text" wire:model="p.emergency_name" class="form-input">
            </div>
            <div>
                <label class="form-label" for="emergency_contact_no">Contact no.</label>
                <input id="emergency_contact_no" type="text" wire:model="p.emergency_contact_no" class="form-input">
            </div>
            <div>
                <label class="form-label" for="emergency_relationship">Relationship</label>
                <input id="emergency_relationship" type="text" wire:model="p.emergency_relationship" class="form-input">
            </div>
        </div>
        <div class="mt-4">
            <label class="form-label" for="emergency_address">Address</label>
            <textarea id="emergency_address" wire:model="p.emergency_address" rows="2" class="form-input"></textarea>
        </div>
    </div>
</section>
