    </div>
    <!-- End Main Content -->

    <footer class="footer">

        <div class="footer-content">

            <p>
                Dairy Farm Management System |
                An AI Powered Solution for Efficient Running of Dairy Farm Operations.
                |
                <?php echo date('d-m-Y'); ?>
            </p>

        </div>

    </footer>

    <!-- Font Awesome -->

    <script
    src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/js/all.min.js">
    </script>

    <!-- Chart.js -->

    <script
    src="https://cdn.jsdelivr.net/npm/chart.js">
    </script>

    <!-- Main Application JS -->

    <script src="assets/js/app.js"></script>

    <!-- Dropdown Script Backup -->

    <script>

    document.addEventListener("DOMContentLoaded", function(){

        const dropdownButtons =
        document.querySelectorAll(".dropdown-btn");

        dropdownButtons.forEach(button => {

            button.addEventListener("click", function(){

                const dropdown =
                this.closest(".dropdown");

                dropdown.classList.toggle("active");

            });

        });

    });

    </script>

</body>

</html>