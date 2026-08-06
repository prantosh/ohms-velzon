/*
Template Name: ABSSRK - Admin & Dashboard Template
Author: Prantosh Deb, BE (Mech), MBA, MCA
Website: https://abssrk.online
Contact: info@abssrk.online
File: Project overview init js
*/

// favourite btn
var favouriteBtn = document.querySelectorAll(".favourite-btn");
if (favouriteBtn) {
    Array.from(document.querySelectorAll(".favourite-btn")).forEach(function (item) {
        item.addEventListener("click", function (event) {
            this.classList.toggle("active");
        });
    });
}