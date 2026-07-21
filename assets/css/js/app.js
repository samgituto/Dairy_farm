document.addEventListener("DOMContentLoaded", e => {
    const dropdownBtns =
        document.querySelectorAll(".dropdown-btn");
    console.log(dropdownBtns);
    dropdownBtns.forEach(btn => {

        btn.addEventListener("click", function () {

            const dropdown =
                this.closest(".dropdown");

            dropdown.classList.toggle("active");

        });

    });
    const dropdowns =
    document.querySelectorAll(".dropdown-btn");

    dropdowns.forEach(button => {

        button.addEventListener("click", function(){

            this.parentElement.classList.toggle("active");

        });

    });
});
