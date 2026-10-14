@props([
    'id',
    'title' => 'Confirm',
    'message' => 'Are you sure?',
    'confirmLabel' => 'Confirm',
    'cancelLabel' => 'Cancel',
    'action' => '#',
    'method' => 'POST',
    'visible' => false,
    'variant' => 'danger',
])

<x-ui.modal :id="$id" :visible="$visible">
    <x-slot:header>
        <div class="ui-modal-heading">
            <div class="ui-modal-title">{{ $title }}</div>
            <x-ui.button type="button" variant="secondary" size="sm" class="ui-button--icon" onclick="document.getElementById('{{ $id }}').style.display='none'" aria-label="Close" title="Close">
                <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M5 5L15 15M15 5L5 15" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" /></svg>
            </x-ui.button>
        </div>
    </x-slot:header>

    <form method="POST" action="{{ $action }}" id="{{ $id }}-form">
        @csrf
        @if(!in_array(strtoupper($method), ['GET', 'POST'], true))
            @method($method)
        @endif
        <p class="ui-body-text">{{ $message }}</p>
    </form>
    <x-slot:footer>
        <x-ui.button type="button" variant="secondary" onclick="document.getElementById('{{ $id }}').style.display='none'">{{ $cancelLabel }}</x-ui.button>
        <x-ui.button type="submit" :variant="$variant" :form="$id.'-form'">{{ $confirmLabel }}</x-ui.button>
    </x-slot:footer>
</x-ui.modal>
