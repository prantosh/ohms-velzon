/*
File: Lock Screen init js
*/

if (document.getElementById('lockscreenForm')) {
    document.getElementById('lockscreenForm').addEventListener('submit', function (e) {
        e.preventDefault();

        var form = e.target;
        var unlockUrl = form.getAttribute('data-unlock-url');
        var csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        fetch(unlockUrl, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({
                password: document.getElementById('userpassword').value,
            }),
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                });
            })
            .then(function (result) {
                if (result.ok && result.data.status) {
                    window.location.href = result.data.redirect;
                } else {
                    var msg = result.data.message || 'Incorrect password. Please try again.';
                    if (result.data.errors) {
                        msg = Object.values(result.data.errors).flat().join('\n');
                    }
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: msg,
                    });
                }
            })
            .catch(function () {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Unable to unlock. Please try again.',
                });
            });
    });
}
