
const sidebarToggle = document.getElementById("sidebarToggle");
const sidebar = document.getElementById("sidebar");

if (sidebarToggle && sidebar) {

    sidebarToggle.addEventListener("click", function () {
        sidebar.classList.toggle("show");
    });

    document.addEventListener("click", function (event) {

        const isMobile = window.innerWidth <= 750;

        if (
            isMobile &&
            !sidebar.contains(event.target) &&
            !sidebarToggle.contains(event.target)
        ) {
            sidebar.classList.remove("show");
        }

    });

}
