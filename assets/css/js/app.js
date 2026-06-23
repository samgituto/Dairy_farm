document.addEventListener("DOMContentLoaded", function () {

    const dropdownBtns =
        document.querySelectorAll(".dropdown-btn");

    dropdownBtns.forEach(btn => {

        btn.addEventListener("click", function () {

            const dropdown =
                this.closest(".dropdown");

            dropdown.classList.toggle("active");

        });

    });

});
document.addEventListener("DOMContentLoaded", function(){

    const dropdowns =
    document.querySelectorAll(".dropdown-btn");

    dropdowns.forEach(button => {

        button.addEventListener("click", function(){

            this.parentElement.classList.toggle("active");

        });

    });

});