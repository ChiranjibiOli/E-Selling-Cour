document.addEventListener("DOMContentLoaded", function () {
    const openLogoutModal = document.getElementById("openLogoutModal");
    const logoutModal = document.getElementById("logoutModal");
    const cancelLogout = document.getElementById("cancelLogout");

    if (openLogoutModal && logoutModal && cancelLogout) {
        openLogoutModal.addEventListener("click", function () {
            logoutModal.classList.add("show");
        });

        cancelLogout.addEventListener("click", function () {
            logoutModal.classList.remove("show");
        });

        logoutModal.addEventListener("click", function (event) {
            if (event.target === logoutModal) {
                logoutModal.classList.remove("show");
            }
        });
    }
});