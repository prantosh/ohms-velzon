"use strict";

/*
Template Name: ABSSRK - Admin & Dashboard Template
File: Staff Password Reset (mobile + WhatsApp OTP) Js File
*/

let verificationToken = null;
let verifiedMobile = null;

const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

function showStep(stepId) {
    document.querySelectorAll('.reset-step').forEach(function (el) {
        el.classList.remove('active');
    });
    document.getElementById(stepId).classList.add('active');
}

function postJson(url, data) {
    return fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json'
        },
        body: new URLSearchParams(Object.assign({ _token: csrfToken }, data))
    }).then(function (response) {
        return response.json();
    });
}

/*
|--------------------------------------------------------------------------
| OTP -- SEND
|--------------------------------------------------------------------------
*/

document.getElementById('sendOtpBtn').addEventListener('click', function () {

    let mobile = document.getElementById('mobile_no').value.trim();

    if (!/^[1-9][0-9]{9}$/.test(mobile)) {
        Swal.fire({ icon: 'warning', title: 'Enter a valid 10 digit mobile number' });
        return;
    }

    let btn = this;
    btn.disabled = true;
    btn.textContent = 'Sending...';

    postJson('/password/send-otp', { mobile_no: mobile }).then(function (response) {

        btn.disabled = false;
        btn.textContent = 'Send OTP';

        if (!response.status) {
            Swal.fire({ icon: 'error', title: 'Could not send OTP', text: response.message });
            return;
        }

        verifiedMobile = mobile;

        document.querySelectorAll('.otp-box').forEach(function (el) { el.value = ''; });
        document.querySelector('#otpInputs .otp-box').focus();

        showStep('step-otp');

        Swal.fire({ icon: 'success', title: 'OTP Sent', text: response.message, timer: 2000, showConfirmButton: false });

    }).catch(function () {
        btn.disabled = false;
        btn.textContent = 'Send OTP';
        Swal.fire({ icon: 'error', title: 'Something went wrong. Please try again.' });
    });
});

document.getElementById('resendOtpBtn').addEventListener('click', function () {
    document.getElementById('mobile_no').value = '';
    showStep('step-mobile');
});

/*
|--------------------------------------------------------------------------
| OTP INPUT BOXES -- auto-advance
|--------------------------------------------------------------------------
*/

document.querySelectorAll('.otp-box').forEach(function (input, index, list) {

    input.addEventListener('input', function () {

        this.value = this.value.replace(/\D/g, '').substring(0, 1);

        if (this.value.length === 1 && list[index + 1]) {
            list[index + 1].focus();
        }
    });

    input.addEventListener('keydown', function (e) {

        if (e.key === 'Backspace' && this.value === '' && list[index - 1]) {
            list[index - 1].focus();
        }
    });
});

/*
|--------------------------------------------------------------------------
| OTP -- VERIFY
|--------------------------------------------------------------------------
*/

document.getElementById('verifyOtpBtn').addEventListener('click', function () {

    let code = '';
    document.querySelectorAll('.otp-box').forEach(function (el) { code += el.value; });

    if (code.length !== 6) {
        Swal.fire({ icon: 'warning', title: 'Enter the complete 6 digit OTP' });
        return;
    }

    let btn = this;
    btn.disabled = true;
    btn.textContent = 'Verifying...';

    postJson('/password/verify-otp', { mobile_no: verifiedMobile, otp_code: code }).then(function (response) {

        btn.disabled = false;
        btn.textContent = 'Verify OTP';

        if (!response.status) {
            Swal.fire({ icon: 'error', title: 'Verification Failed', text: response.message });
            return;
        }

        verificationToken = response.verification_token;

        document.getElementById('reset_mobile_no').value = verifiedMobile;
        document.getElementById('reset_verification_token').value = verificationToken;

        showStep('step-password');

    }).catch(function () {
        btn.disabled = false;
        btn.textContent = 'Verify OTP';
        Swal.fire({ icon: 'error', title: 'Something went wrong. Please try again.' });
    });
});
