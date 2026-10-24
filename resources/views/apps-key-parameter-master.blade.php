@extends('layouts.master')

@section('title')
    Pathology Key Parameter Groups
@endsection

@section('content')

@component('components.breadcrumb')
    @slot('li_1')
        Diagnostic Test
    @endslot
    @slot('title')
        Pathology Key Parameter Groups
    @endslot
@endcomponent

<div class="alert alert-info">
    A group is an invoice item with a non-zero rate. Add the report items captured under that billed item;
    those items may have a zero or non-zero rate. Packages are available as groups too; their report items are
    identified separately as being under the package. Select a group to move its items up or down; order starts
    at 0 within each group.
</div>

<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="d-flex flex-wrap gap-2">
            <select id="groupFilter" class="form-select">
                <option value="">All invoice items</option>
            </select>
            <input id="searchFilter" class="form-control" type="search" placeholder="Search invoice item, parameter or code">
        </div>
        <button id="addParameterButton" class="btn btn-primary" type="button">Add report item</button>
    </div>
    <div class="card-body">
        <div id="pageError" class="alert alert-danger d-none"></div>
        <p class="text-muted">Use the <strong>Up</strong> and <strong>Down</strong> buttons in the Order column to reorder items within their invoice group or package.</p>
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Group invoice item / package</th>
                        <th>Section</th>
                        <th>Report item</th>
                        <th>Invoice item</th>
                        <th>Rate</th>
                        <th>Captured when billed item is</th>
                        <th>Unit</th>
                        <th>Default value</th>
                        <th>Order</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="parameterRows"></tbody>
            </table>
        </div>
        <div id="emptyState" class="text-muted text-center py-4 d-none">No key parameters found.</div>
    </div>
</div>

<div class="modal fade" id="parameterModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form id="parameterForm">
                @csrf
                <div class="modal-header">
                    <h5 id="modalTitle" class="modal-title">Add report item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="formError" class="alert alert-danger d-none"></div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="groupItem" class="form-label">Group invoice item</label>
                            <select id="groupItem" class="form-select">
                                <option value="">Standalone invoice item (no group)</option>
                            </select>
                            <small class="text-muted">Any active invoice item with a non-zero rate may be a group, including package items.</small>
                        </div>
                        <div class="col-md-6">
                            <label for="parameterItem" class="form-label">Report item</label>
                            <select id="parameterItem" class="form-select" required></select>
                            <small class="text-muted">Choose an invoice item to capture under the group. Its rate may be zero.</small>
                        </div>
                        <div class="col-md-6">
                            <label for="sectionLabel" class="form-label">Section heading</label>
                            <input id="sectionLabel" class="form-control" maxlength="60" list="sectionLabelOptions" placeholder="e.g. PHYSICAL">
                            <datalist id="sectionLabelOptions"></datalist>
                            <small class="text-muted">Optional. Printed as a heading row above this item when it differs from the previous item's section.</small>
                        </div>
                        <div class="col-md-6">
                            <label for="defaultValue" class="form-label">Default value</label>
                            <input id="defaultValue" class="form-control" maxlength="100" placeholder="e.g. Nil">
                            <small class="text-muted">Optional. Pre-filled on new reports; staff can change it.</small>
                        </div>
                        <div class="col-md-3">
                            <label for="parameterUom" class="form-label">Unit</label>
                            <input id="parameterUom" class="form-control" maxlength="50">
                        </div>
                        <div class="col-md-3">
                            <label for="sortOrder" class="form-label">Display order</label>
                            <input id="sortOrder" class="form-control" type="number" min="0" max="65535" value="0" required>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input id="parameterActive" class="form-check-input" type="checkbox" checked>
                                <label class="form-check-label" for="parameterActive">Active (show on new pathology reports)</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input id="triggerWhenItemBilled" class="form-check-input" type="checkbox">
                                <label class="form-check-label" for="triggerWhenItemBilled">Also capture when this report item is billed individually</label>
                            </div>
                            <small class="text-muted">This option is available only when the report item's rate is non-zero. The group invoice item always triggers its report items.</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button id="saveParameterButton" class="btn btn-success" type="submit">Save report item</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('script')
<script>
(() => {
    const listUrl = @json(route('pathology-key-parameters.list'));
    const optionsUrl = @json(route('pathology-key-parameters.options'));
    const saveUrl = @json(route('pathology-key-parameters.save'));
    const reorderUrl = @json(route('pathology-key-parameters.reorder'));
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const modal = new bootstrap.Modal(document.getElementById('parameterModal'));
    const groupItemSelect = document.getElementById('groupItem');
    const itemSelect = document.getElementById('parameterItem');
    const rowsBody = document.getElementById('parameterRows');
    const groupFilter = document.getElementById('groupFilter');
    const searchFilter = document.getElementById('searchFilter');
    let testItems = [];
    let groupItems = [];
    let parameters = [];
    let editingId = null;

    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    })[char]);

    async function request(url, options = {}) {
        const response = await fetch(url, {
            ...options,
            headers: {
                Accept: 'application/json',
                ...(options.body ? { 'Content-Type': 'application/json' } : {}),
                ...(options.headers || {})
            }
        });
        const body = await response.json();
        if (!response.ok || body.status === false) {
            const errors = body.errors ? Object.values(body.errors).flat().join(' ') : body.message;
            throw new Error(errors || `Request failed (${response.status}).`);
        }
        return body;
    }

    function setError(element, message = '') {
        element.textContent = message;
        element.classList.toggle('d-none', !message);
    }

    function fillTestOptions() {
        const selectedItem = itemSelect.value;
        const optionHtml = testItems.map(item => {
            const label = `${item.category_name} — ${item.item_description_sub} (${item.item_code_sub})`;
            return `<option value="${escapeHtml(item.item_code_sub)}">${escapeHtml(label)}</option>`;
        }).join('');

        itemSelect.innerHTML = '<option value="">Select a test item</option>' + optionHtml;
        const selectedGroupItem = groupItemSelect.value;
        groupItemSelect.innerHTML = '<option value="">Standalone invoice item (no group)</option>' +
            groupItems.map(item => {
                const packageLabel = item.is_package ? ' — Package group' : '';
                const label = `${item.category_name} — ${item.item_description_sub} (${item.item_code_sub})${packageLabel}`;
                return `<option value="${escapeHtml(item.item_code_sub)}">${escapeHtml(label)}</option>`;
            }).join('');

        if (selectedItem && testItems.some(item => item.item_code_sub === selectedItem)) {
            itemSelect.value = selectedItem;
        }
        groupItemSelect.value = selectedGroupItem;
        updateIndividualBillingOption();
    }

    function updateIndividualBillingOption() {
        const selectedItem = testItems.find(item => item.item_code_sub === itemSelect.value);
        const checkbox = document.getElementById('triggerWhenItemBilled');
        checkbox.disabled = !groupItemSelect.value || !selectedItem || Number(selectedItem.rate) === 0;
        if (checkbox.disabled) checkbox.checked = false;
    }

    async function loadData() {
        const query = new URLSearchParams();
        if (groupFilter.value) query.set('group_item_code_sub', groupFilter.value);
        if (searchFilter.value.trim()) query.set('search', searchFilter.value.trim());

        const [list, options] = await Promise.all([
            request(`${listUrl}?${query}`),
            testItems.length ? Promise.resolve(null) : request(optionsUrl)
        ]);

        if (options) {
            testItems = options.data;
            groupItems = options.group_items;
            fillTestOptions();
        }

        parameters = list.data;
        document.getElementById('sectionLabelOptions').innerHTML = [...new Set(
            parameters.map(parameter => parameter.section_label).filter(Boolean)
        )].map(section => `<option value="${escapeHtml(section)}"></option>`).join('');
        const selectedGroup = groupFilter.value;
        const groups = list.groups;
        groupFilter.innerHTML = '<option value="">All invoice items</option>' +
            groups.map(group => {
                const packageLabel = group.is_package ? ' — Package' : '';
                const label = `${group.item_description_sub} (${group.item_code_sub})${packageLabel}`;
                return `<option value="${escapeHtml(group.item_code_sub)}">${escapeHtml(label)}</option>`;
            }).join('') +
            '<option value="_standalone">Standalone items</option>';
        groupFilter.value = selectedGroup;

        const canReorder = !searchFilter.value.trim();
        const parametersByGroup = new Map();
        parameters.forEach(parameter => {
            if (!parameter.group_item_code_sub) return;
            const groupParameters = parametersByGroup.get(parameter.group_item_code_sub) || [];
            groupParameters.push(parameter);
            parametersByGroup.set(parameter.group_item_code_sub, groupParameters);
        });

        rowsBody.innerHTML = parameters.map(parameter => {
            const groupParameters = parametersByGroup.get(parameter.group_item_code_sub) || [];
            const groupIndex = groupParameters.indexOf(parameter);
            const triggerCodes = Array.isArray(parameter.trigger_item_code_subs)
                ? parameter.trigger_item_code_subs
                : [];
            const groupTrigger = parameter.group_item_code_sub
                ? `${parameter.group_item_is_package ? 'Under package: ' : ''}${parameter.group_item_description} (${parameter.group_item_code_sub})`
                : 'Standalone item';
            const individualTrigger = triggerCodes.includes(parameter.item_code_sub)
                && parameter.group_item_code_sub
                ? `; ${parameter.item_description_sub} (${parameter.item_code_sub})`
                : '';
            return `<tr>
                <td>${escapeHtml(groupTrigger)}</td>
                <td>${escapeHtml(parameter.section_label || '—')}</td>
                <td>${escapeHtml(parameter.report_item_name || parameter.item_description_sub || 'Unknown invoice item')}</td>
                <td>${escapeHtml(parameter.item_description_sub || 'Unknown invoice item')} (${escapeHtml(parameter.item_code_sub)})</td>
                <td>${escapeHtml(Number(parameter.item_rate || 0).toFixed(2))}</td>
                <td>${escapeHtml(groupTrigger === 'Standalone item' ? `${parameter.item_description_sub} (${parameter.item_code_sub})` : `${groupTrigger}${individualTrigger}`)}</td>
                <td>${escapeHtml(parameter.uom || '—')}</td>
                <td>${escapeHtml(parameter.default_value || '—')}</td>
                <td>
                    ${escapeHtml(parameter.sort_order)}
                    ${canReorder && parameter.group_item_code_sub ? `
                        <button class="btn btn-sm btn-outline-secondary reorder-button" data-id="${escapeHtml(parameter.id)}" data-direction="-1" type="button" aria-label="Move ${escapeHtml(parameter.report_item_name || parameter.item_description_sub)} up" ${groupIndex === 0 ? 'disabled' : ''}>Up</button>
                        <button class="btn btn-sm btn-outline-secondary reorder-button" data-id="${escapeHtml(parameter.id)}" data-direction="1" type="button" aria-label="Move ${escapeHtml(parameter.report_item_name || parameter.item_description_sub)} down" ${groupIndex === groupParameters.length - 1 ? 'disabled' : ''}>Down</button>
                    ` : ''}
                </td>
                <td><span class="badge ${parameter.is_active ? 'bg-success' : 'bg-secondary'}">${parameter.is_active ? 'Active' : 'Inactive'}</span></td>
                <td><button class="btn btn-sm btn-outline-primary edit-button" data-id="${escapeHtml(parameter.id)}" type="button">Edit</button></td>
            </tr>`;
        }).join('');

        document.getElementById('emptyState').classList.toggle('d-none', parameters.length > 0);
        rowsBody.querySelectorAll('.edit-button').forEach(button => {
            button.addEventListener('click', () => editParameter(Number(button.dataset.id)));
        });
        rowsBody.querySelectorAll('.reorder-button').forEach(button => {
            button.addEventListener('click', () => moveParameter(button));
        });
    }

    async function moveParameter(button) {
        const parameter = parameters.find(item => Number(item.id) === Number(button.dataset.id));
        if (!parameter || !parameter.group_item_code_sub) return;
        const groupParameters = parameters.filter(item =>
            item.group_item_code_sub === parameter.group_item_code_sub
        );
        const currentIndex = groupParameters.findIndex(item => item.id === parameter.id);
        const targetIndex = currentIndex + Number(button.dataset.direction);
        if (targetIndex < 0 || targetIndex >= groupParameters.length) return;

        [groupParameters[currentIndex], groupParameters[targetIndex]] =
            [groupParameters[targetIndex], groupParameters[currentIndex]];
        try {
            await request(reorderUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({
                    group_item_code_sub: parameter.group_item_code_sub,
                    item_code_subs: groupParameters.map(item => item.item_code_sub)
                })
            });
            await loadData();
        } catch (error) {
            await loadData();
            setError(document.getElementById('pageError'), error.message);
        }
    }

    function openNewForm() {
        editingId = null;
        document.getElementById('parameterForm').reset();
        document.getElementById('modalTitle').textContent = 'Add report item';
        document.getElementById('parameterActive').checked = true;
        document.getElementById('sortOrder').value = '0';
        groupItemSelect.disabled = false;
        itemSelect.disabled = false;
        fillTestOptions();
        setError(document.getElementById('formError'));
        modal.show();
    }

    function editParameter(id) {
        const parameter = parameters.find(row => Number(row.id) === id);
        if (!parameter) return;
        const code = parameter.item_code_sub;
        editingId = id;
        document.getElementById('modalTitle').textContent = 'Edit report item';
        groupItemSelect.disabled = false;
        itemSelect.disabled = true;
        fillTestOptions();
        itemSelect.value = code;
        groupItemSelect.value = parameter.group_item_code_sub || '';
        document.getElementById('parameterUom').value = parameter.uom || '';
        document.getElementById('sectionLabel').value = parameter.section_label || '';
        document.getElementById('defaultValue').value = parameter.default_value || '';
        document.getElementById('sortOrder').value = parameter.sort_order;
        document.getElementById('parameterActive').checked = Boolean(parameter.is_active);
        document.getElementById('triggerWhenItemBilled').checked =
            Boolean(parameter.group_item_code_sub && (parameter.trigger_item_code_subs || []).includes(code));
        updateIndividualBillingOption();
        setError(document.getElementById('formError'));
        modal.show();
    }

    itemSelect.addEventListener('change', () => {
        const item = testItems.find(candidate => candidate.item_code_sub === itemSelect.value);
        if (item && !document.getElementById('parameterUom').value && item.uom) {
            document.getElementById('parameterUom').value = item.uom;
        }
        updateIndividualBillingOption();
    });

    groupItemSelect.addEventListener('change', updateIndividualBillingOption);
    document.getElementById('addParameterButton').addEventListener('click', openNewForm);
    groupFilter.addEventListener('change', () => loadData().catch(error => setError(document.getElementById('pageError'), error.message)));
    let searchTimer;
    searchFilter.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => loadData().catch(error => setError(document.getElementById('pageError'), error.message)), 250);
    });

    document.getElementById('parameterForm').addEventListener('submit', async event => {
        event.preventDefault();
        try {
            await request(saveUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({
                    id: editingId,
                    item_code_sub: itemSelect.value,
                    group_item_code_sub: groupItemSelect.value || null,
                    uom: document.getElementById('parameterUom').value,
                    section_label: document.getElementById('sectionLabel').value,
                    default_value: document.getElementById('defaultValue').value,
                    trigger_when_item_billed: document.getElementById('triggerWhenItemBilled').checked,
                    sort_order: document.getElementById('sortOrder').value,
                    is_active: document.getElementById('parameterActive').checked
                })
            });
            modal.hide();
            await loadData();
            if (window.Swal) Swal.fire({ icon: 'success', title: 'Saved', text: 'Pathology parameter saved.' });
        } catch (error) {
            setError(document.getElementById('formError'), error.message);
        }
    });

    loadData().catch(error => setError(document.getElementById('pageError'), error.message));
})();
</script>
@endsection
