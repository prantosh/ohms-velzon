// Auto-initializes any .flatpickr input as a dd-mm-yyyy (Indian format) date
// picker. The underlying input keeps its Y-m-d value (via altInput) so any
// existing JS reading .value / .val() by id, or a native form submit, is
// unaffected -- only the visible text changes to dd-mm-yyyy.
document.addEventListener('DOMContentLoaded', function () {
    if (typeof flatpickr === 'undefined') return;

    document.querySelectorAll('.flatpickr').forEach(function (el) {
        if (el._flatpickr) return;

        flatpickr(el, {
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'd-m-Y',
            allowInput: true,
        });
    });
});

// Legacy MySQL "zero dates" (0000-00-00) and other garbage strings parse into
// nonsense via flatpickr's date parser (unlike a native date input, which
// silently rejects them) -- treat anything that isn't a real calendar date
// as empty.
function isValidDmyDateString(value) {
    if (!value) return false;

    var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(value));
    if (!m) return false;

    var year = parseInt(m[1], 10), month = parseInt(m[2], 10), day = parseInt(m[3], 10);
    if (year < 1000 || month < 1 || month > 12 || day < 1 || day > 31) return false;

    var d = new Date(year, month - 1, day);
    return d.getFullYear() === year && d.getMonth() === month - 1 && d.getDate() === day;
}

// Programmatically setting .value / jQuery .val() on a flatpickr-controlled
// input does NOT refresh its visible altInput -- always use this instead
// (accepts a plain element, an id string, or a jQuery/array-like wrapper)
// so defaults, edit-mode population, and form resets show the right date.
function setFlatpickrValue(elOrId, value) {
    var el = typeof elOrId === 'string'
        ? document.getElementById(elOrId)
        : (elOrId && elOrId.jquery ? elOrId.get(0) : elOrId);

    if (!el) return;

    if (el._flatpickr) {
        el._flatpickr.setDate(isValidDmyDateString(value) ? value : '', true);
    } else {
        el.value = isValidDmyDateString(value) ? value : '';
    }
}
