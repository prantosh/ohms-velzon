window.Loader = {

    show(message = "Loading...") {

        $("#globalLoaderText").text(message);

        $("#globalLoader").fadeIn(100);

    },

    hide() {

        $("#globalLoader").fadeOut(100);

    }

};
