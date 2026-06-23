<div class="sidebar">

    <div class="logo-section">
        <h4> Dairy Farm App</h4>
    </div>

    <ul class="sidebar-menu">

        <li>
            <a href="dashboard.php">
                <i class="fas fa-home"></i>
                Dashboard
            </a>
        </li>

        <!-- HERD MANAGEMENT -->
        <li>
            <a href="cows.php">
                <i class="fas fa-cow"></i>
                Herd Management
            </a>
        </li>

        <!-- MILK PRODUCTION -->
        <li>
            <a href="milk.php">
                <i class="fas fa-glass-water"></i>
                Milk Production
            </a>
        </li>

        <!-- HEALTH & BREEDING -->
        <li class="dropdown">

            <button class="dropdown-btn">

                <span>
                    <i class="fas fa-heartbeat"></i>
                    Health & Breeding
                </span>

                <i class="fas fa-chevron-down"></i>

            </button>

            <ul class="dropdown-menu">

                <li>
                    <a href="vaccinations.php">
                        Vaccinations
                    </a>
                </li>

                <li>
                    <a href="treatments.php">
                        Treatments
                    </a>
                </li>

                <li>
                    <a href="breeding.php">
                        Breeding Records
                    </a>
                </li>

                <li>
                    <a href="pregnancy.php">
                        Pregnancy Records
                    </a>
                </li>

                <li>
                    <a href="calving.php">
                        Calving Records
                    </a>
                </li>

                <li>
                    <a href="health_reports.php">
                        Health Reports
                    </a>
                </li>

            </ul>

        </li>

        <!-- FEED & INVENTORY -->

        <li class="dropdown">

<button type="button" class="dropdown-btn">

<span>

<i class="fas fa-boxes"></i>

Feed & Inventory

</span>

<i class="fas fa-chevron-down"></i>

</button>

         <ul class="dropdown-menu">

                <li>

                    <a href="feed_ingredients.php">

                        Feed Ingredients

                    </a>

                </li>

                <li>

                    <a href="feed_formulations.php">

                        Feed Formulations

                    </a>

                </li>

                <li>

                    <a href="mix_feed.php">

                        Feed Mixing

                    </a>

                </li>

                <li>

                    <a href="inventory.php">

                        Inventory

                    </a>

                </li>

                <li>

                    <a href="feed_reports.php">

                        Feed Reports

                    </a>

                </li>

            </ul>

        </li>

</li>
        

        <!-- FINANCE -->

        <li>
            <a href="finance.php">
                <i class="fas fa-money-bill-wave"></i>
                Finance
            </a>
        </li>

        <!-- REPORTS -->

        <li>
            <a href="reports.php">
                <i class="fas fa-chart-line"></i>
                Reports & Analytics
            </a>
        </li>

        <!-- AI -->

        <li>
            <a href="ai_assistant.php">
                <i class="fas fa-robot"></i>
                AI Assistant
            </a>
        </li>

    </ul>

    <div class="sidebar-footer">

        <a href="logout.php">

            <i class="fas fa-sign-out-alt"></i>

            Logout

        </a>

    </div>

</div>
<?php include 'includes/footer.php'; ?>