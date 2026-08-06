/*
Template Name: ABSSRK - Admin & Dashboard Template
Author: Prantosh Deb, BE (Mech), MBA, MCA
Website: https://abssrk.online
Contact: info@abssrk.online
File: Two step verification Init Js File
*/

// move next

function getInputElement(index) {
    return document.getElementById('digit' + index + '-input');
}
function moveToNext(index, event) {
    const eventCode = event.which || event.keyCode;
    if (getInputElement(index).value.length === 1) {
        if (index !== 4) {
            getInputElement(index + 1).focus();
        } else {
            getInputElement(index).blur();
            // Submit code
            console.log('submit code');
        }
    }
    if (eventCode === 8 && index !== 1) {
        getInputElement(index - 1).focus();
    }
}