/**
 * Applies to every patient-name input across the app (matched by id
 * containing "patient" + "name" in either underscore or hyphen form, e.g.
 * patient_name, patient_name-field, patient_name1-field, swal-patient-name)
 * -- including fields created dynamically after page load (SweetAlert
 * forms etc.), since this listens via delegation on document rather than
 * binding to elements present at DOMContentLoaded.
 *
 * Restricts input to letters, spaces and '.', and forces uppercase as the
 * user types, preserving cursor position.
 */
(function () {

    function isPatientNameField(el) {

        if (!el || !el.id) return false;
        if (el.tagName !== 'INPUT' && el.tagName !== 'TEXTAREA') return false;

        return /patient.?name/i.test(el.id);
    }

    function sanitize(el) {

        var original = el.value;
        var cleaned = original.toUpperCase().replace(/[^A-Z .]/g, '');

        if (cleaned === original) return;

        var cursorPos = el.selectionStart;
        var beforeCursorCleaned = original
            .substring(0, cursorPos)
            .toUpperCase()
            .replace(/[^A-Z .]/g, '');

        el.value = cleaned;

        if (typeof el.setSelectionRange === 'function') {
            el.setSelectionRange(beforeCursorCleaned.length, beforeCursorCleaned.length);
        }
    }

    document.addEventListener('input', function (e) {

        if (!isPatientNameField(e.target)) return;

        sanitize(e.target);
    });
})();
