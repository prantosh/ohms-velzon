function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]').content;
}

document.querySelectorAll('.autoSendToggle').forEach(function (checkbox) {

    checkbox.addEventListener('change', async function () {

        let messageType = this.dataset.messageType;
        let isEnabled = this.checked;
        let checkboxEl = this;

        // Turning OFF is the consequential direction (especially for the two
        // OTP categories, which have no other delivery channel) -- confirm
        // before committing, same convention as Maintenance Mode's ON confirm.
        if (!isEnabled) {
            let confirmResult = await Swal.fire({
                title: 'Turn OFF automatic sending?',
                text: 'This category will stop sending WhatsApp messages automatically. The manual "Send WhatsApp" button elsewhere in the app will keep working.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#f06548',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Turn It OFF'
            });

            if (!confirmResult.isConfirmed) {
                checkboxEl.checked = true;
                return;
            }
        }

        checkboxEl.disabled = true;

        try {

            const response = await fetch('/whatsapp-auto-send-settings/toggle', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ message_type: messageType, is_enabled: isEnabled })
            });

            const result = await response.json();

            if (!result.status) {
                checkboxEl.checked = !isEnabled;
            }

            Swal.fire({
                icon: result.status ? 'success' : 'error',
                title: result.status ? 'Saved' : 'Error',
                text: result.message,
                timer: result.status ? 1500 : undefined,
                showConfirmButton: !result.status
            });

        } catch (e) {

            checkboxEl.checked = !isEnabled;
            Swal.fire({ icon: 'error', title: 'Error', text: 'Could not save this setting. Please try again.' });

        } finally {

            checkboxEl.disabled = false;
        }
    });
});
