@php
    $modalId = $modalId ?? 'assignment-modal';
    $title = $title ?? 'Assign';
    $formAction = $formAction ?? '#';
    $inputName = $inputName ?? 'item_ids[]';
    $availableItems = collect($availableItems ?? []);
@endphp

<x-ui.modal :id="$modalId" max-width="720px" modal-class="assignment-modal-shell">
    <x-slot:header>
        <div class="ui-modal-heading">
            <div class="modal-title ui-modal-title" id="{{ $modalId }}-title">{{ $title }}</div>
            <x-ui.button
                type="button"
                variant="secondary"
                size="sm"
                class="ui-button--icon"
                aria-label="Close"
                title="Close"
                onclick="document.getElementById('{{ $modalId }}').style.display='none'"
            >
                <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <path d="M5 5L15 15M15 5L5 15" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                </svg>
            </x-ui.button>
        </div>
    </x-slot:header>
    <form method="POST" action="{{ $formAction }}" id="{{ $modalId }}-form" class="ui-modal-form" data-dashboard-async>
        @csrf
        <div class="alert alert-danger mb-3" data-form-errors style="display:none"></div>
        <div class="assignment-modal-search">
            <x-ui.search
                :id="$modalId.'-search'"
                name="_assignment_search"
                placeholder="Search by name"
                aria-label="Search available items"
            />
            <div class="assignment-modal-dropdown" id="{{ $modalId }}-dropdown">
                <div class="assignment-modal-list" id="{{ $modalId }}-list">
                    <div class="assignment-modal-hint" id="{{ $modalId }}-hint">No Records</div>
                    @forelse($availableItems as $item)
                        @php
                            $itemId = data_get($item, 'id');
                            $itemTitle = data_get($item, 'title', data_get($item, 'name'));
                            $itemMeta = data_get($item, 'meta');
                            $itemBadge = data_get($item, 'badge');
                            $searchText = strtolower(trim($itemTitle));
                        @endphp
                        <label class="assignment-option" data-search="{{ $searchText }}" data-item-id="{{ $itemId }}" data-item-title="{{ $itemTitle }}">
                            <input type="checkbox" name="{{ $inputName }}" value="{{ $itemId }}" class="assignment-checkbox">
                            <div class="assignment-option-body">
                                <div class="assignment-option-title">{{ $itemTitle }}</div>
                                @if($itemMeta)
                                    <div class="assignment-option-meta">{{ $itemMeta }}</div>
                                @endif
                            </div>
                            @if($itemBadge)
                                <span class="ui-badge ui-badge--secondary">{{ $itemBadge }}</span>
                            @endif
                        </label>
                    @empty
                        <div class="assignment-modal-empty">No Records</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="ui-surface-block" style="gap:14px">
            <div class="assignment-modal-label">Selected Items</div>
            <div class="assignment-modal-chips" id="{{ $modalId }}-chips"></div>
        </div>
    </form>
    <x-slot:footer>
        <x-ui.button type="button" variant="secondary" onclick="document.getElementById('{{ $modalId }}').style.display='none'">Cancel</x-ui.button>
        <x-ui.button type="submit" :form="$modalId.'-form'">Save</x-ui.button>
    </x-slot:footer>
</x-ui.modal>

@once
    @push('scripts')
        <script>
            window.ArcherAssignmentModal = window.ArcherAssignmentModal || {
                init(modalId) {
                    const modal = document.getElementById(modalId);
                    if (!modal || modal.dataset.assignmentReady === '1') return;

                    modal.dataset.assignmentReady = '1';

                    const form = modal.querySelector('form');
                    const title = modal.querySelector('.modal-title');
                    const search = modal.querySelector('input[type="text"]');
                    const searchWrap = modal.querySelector('.assignment-modal-search');
                    const chips = modal.querySelector('.assignment-modal-chips');
                    const hint = modal.querySelector('.assignment-modal-hint');
                    const dropdown = modal.querySelector('.assignment-modal-dropdown');
                    const options = () => Array.from(modal.querySelectorAll('.assignment-option'));
                    const boxes = () => Array.from(modal.querySelectorAll('.assignment-checkbox'));
                    const openDropdown = () => {
                        dropdown?.classList.add('is-open');
                        searchWrap?.classList.add('is-open');
                    };
                    const closeDropdown = () => {
                        dropdown?.classList.remove('is-open');
                        searchWrap?.classList.remove('is-open');
                    };

                    const renderChips = () => {
                        const selected = options().filter(option => option.querySelector('.assignment-checkbox')?.checked);
                        if (!selected.length) {
                            chips.innerHTML = '<span class="text-muted text-xs">—</span>';
                            return;
                        }

                        chips.innerHTML = selected.map(option => {
                            const id = option.dataset.itemId;
                            const label = option.dataset.itemTitle;
                            return `<span class="assignment-chip">${label}<button type="button" data-remove-id="${id}" aria-label="Remove ${label}"><svg viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M4 4L12 12M12 4L4 12" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" /></svg></button></span>`;
                        }).join('');

                        chips.querySelectorAll('[data-remove-id]').forEach(button => {
                            button.addEventListener('click', () => {
                                const box = modal.querySelector(`.assignment-checkbox[value="${button.dataset.removeId}"]`);
                                if (box) {
                                    box.checked = false;
                                    renderChips();
                                }
                            });
                        });
                    };

                    const filterOptions = () => {
                        const query = (search?.value || '').toLowerCase().trim();
                        let visible = 0;
                        options().forEach(option => {
                            const haystack = option.dataset.search || '';
                            const show = query === '' || haystack.includes(query);
                            option.classList.toggle('is-hidden', !show);
                            if (show) visible += 1;
                        });
                        if (hint) {
                            if (visible === 0) {
                                hint.textContent = 'No Records';
                                hint.style.display = '';
                            } else {
                                hint.style.display = 'none';
                            }
                        }
                    };

                    boxes().forEach(box => {
                        box.addEventListener('change', () => {
                            renderChips();
                            openDropdown();
                        });
                    });
                    search?.addEventListener('input', () => {
                        filterOptions();
                        openDropdown();
                    });
                    search?.addEventListener('focus', () => {
                        filterOptions();
                        openDropdown();
                    });
                    search?.addEventListener('click', () => {
                        filterOptions();
                        openDropdown();
                    });

                    document.addEventListener('click', event => {
                        const target = event.target;
                        if (!modal.contains(target)) {
                            closeDropdown();
                            return;
                        }

                        const clickedInsideSearch = searchWrap?.contains(target);
                        const clickedInsideDropdown = dropdown?.contains(target);
                        if (!clickedInsideSearch && !clickedInsideDropdown) {
                            closeDropdown();
                        }
                    });

                    renderChips();
                }
            };

            (() => {
                if (window.ArcherAssignmentModalTriggerReady) return;
                window.ArcherAssignmentModalTriggerReady = true;

                document.addEventListener('click', (event) => {
                    const trigger = event.target.closest('[data-assignment-modal]');
                    if (!trigger) return;

                    const modalId = trigger.dataset.assignmentModal;
                    if (!modalId) return;

                    window.ArcherAssignmentModal?.init(modalId);

                    const modal = document.getElementById(modalId);
                    if (!modal) return;

                    const form = modal.querySelector('form');
                    const title = modal.querySelector('.modal-title');
                    const search = modal.querySelector('input[type="text"]');
                    const chips = modal.querySelector('.assignment-modal-chips');
                    const dropdown = modal.querySelector('.assignment-modal-dropdown');
                    const searchWrap = modal.querySelector('.assignment-modal-search');
                    const options = Array.from(modal.querySelectorAll('.assignment-option'));
                    const boxes = Array.from(modal.querySelectorAll('.assignment-checkbox'));
                    const hint = modal.querySelector('.assignment-modal-hint');

                    if (title) {
                        title.textContent = trigger.dataset.assignmentTitle || title.textContent;
                    }

                    if (form) {
                        form.action = trigger.dataset.assignmentAction || form.action;
                    }

                    const assigned = (trigger.dataset.assignmentSelected || '')
                        .split(',')
                        .map(value => value.trim())
                        .filter(Boolean);

                    boxes.forEach((box) => {
                        box.checked = assigned.includes(box.value);
                    });

                    if (search) {
                        search.value = '';
                    }

                    let visible = 0;
                    options.forEach((option) => {
                        option.classList.remove('is-hidden');
                        visible += 1;
                    });

                    if (hint) {
                        hint.style.display = visible === 0 ? '' : 'none';
                        hint.textContent = 'No Records';
                    }

                    if (chips) {
                        const selected = options.filter(option => option.querySelector('.assignment-checkbox')?.checked);
                        chips.innerHTML = selected.length
                            ? selected.map(option => {
                                const id = option.dataset.itemId;
                                const label = option.dataset.itemTitle;
                                return `<span class="assignment-chip">${label}<button type="button" data-remove-id="${id}" aria-label="Remove ${label}"><svg viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M4 4L12 12M12 4L4 12" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" /></svg></button></span>`;
                              }).join('')
                            : '<span class="text-muted text-xs">—</span>';

                        chips.querySelectorAll('[data-remove-id]').forEach((button) => {
                            button.addEventListener('click', () => {
                                const box = modal.querySelector(`.assignment-checkbox[value="${button.dataset.removeId}"]`);
                                if (box) {
                                    box.checked = false;
                                    button.closest('.assignment-chip')?.remove();
                                    if (!chips.querySelector('.assignment-chip')) {
                                        chips.innerHTML = '<span class="text-muted text-xs">—</span>';
                                    }
                                }
                            });
                        });
                    }

                    dropdown?.classList.remove('is-open');
                    searchWrap?.classList.remove('is-open');
                    modal.style.display = 'flex';
                });
            })();
        </script>
    @endpush
@endonce
