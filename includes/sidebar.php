<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ============================================================
   GET CURRENT USER ROLE SAFELY
============================================================ */

$currentRole = strtolower(trim($_SESSION['role'] ?? 'guest'));


/* ============================================================
   SIDEBAR ROLE CHECKER
============================================================ */

function sidebarRoleAllowed($allowedRoles)
{
    global $currentRole;

    $allowedRoles = array_map(function ($role) {
        return strtolower(trim($role));
    }, $allowedRoles);

    return in_array($currentRole, $allowedRoles);
}

?>

<div class="sidebar">

    <div class="logo-section">
        <h4>Dairy Farm App</h4>
    </div>

    <ul class="sidebar-menu">

        <!-- DASHBOARD: ALL USERS -->
        <li>
            <a href="dashboard.php">
                <i class="fas fa-home"></i>
                Dashboard
            </a>
        </li>


        <!-- HERD MANAGEMENT: ALL LOGGED USERS -->
        <?php if (sidebarRoleAllowed(['administrator', 'farm_manager', 'veterinarian', 'farm_worker'])) { ?>

            <li>
                <a href="cows.php">
                    <i class="fas fa-cow"></i>
                    Herd Management
                </a>
            </li>

        <?php } ?>


        <!-- MILK PRODUCTION: ADMIN, MANAGER, WORKER -->
        <?php if (sidebarRoleAllowed(['administrator', 'farm_manager', 'farm_worker'])) { ?>

            <li>
                <a href="milk.php">
                    <i class="fas fa-glass-water"></i>
                    Milk Production
                </a>
            </li>

        <?php } ?>


        <!-- HEALTH & BREEDING: ADMIN, MANAGER, VETERINARIAN -->
        <?php if (sidebarRoleAllowed(['administrator', 'farm_manager', 'farm_worker', 'veterinarian'])) { ?>

            <li>
                <div class="dropdown">

                    <button type="button" class="dropdown-btn">

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

                </div>
            </li>

        <?php } ?>


        <!-- FEED & INVENTORY: ADMIN, MANAGER, WORKER -->
        <?php if (sidebarRoleAllowed(['administrator', 'farm_manager', 'farm_worker'])) { ?>

            <li>
                <div class="dropdown">

                    <button type="button" class="dropdown-btn">

                        <span>
                            <i class="fas fa-boxes"></i>
                            Feed & Inventory
                        </span>

                        <i class="fas fa-chevron-down"></i>

                    </button>

                    <ul class="dropdown-menu">

                        <li>
                            <a href="feed_schedules.php">
                                Feed Schedules
                            </a>
                        </li>

                        <?php if (sidebarRoleAllowed(['administrator', 'farm_manager'])) { ?>

                            <li>
                                <a href="formulations.php">
                                    Feed Formulations
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

                        <?php } ?>

                    </ul>

                </div>
            </li>

        <?php } ?>


        <!-- SALES: ADMIN, MANAGER -->
        <?php if (sidebarRoleAllowed(['administrator', 'farm_manager'])) { ?>

            <li>
                <a href="sales.php">
                    <i class="fas fa-dollar-sign"></i>
                    Sales
                </a>
            </li>

        <?php } ?>


        <!-- FINANCE: ADMIN, MANAGER -->
        <?php if (sidebarRoleAllowed(['administrator', 'farm_manager'])) { ?>

            <li>
                <a href="finance.php">
                    <i class="fas fa-money-bill-wave"></i>
                    Finance
                </a>
            </li>

        <?php } ?>


        <!-- REPORTS: ADMIN, MANAGER -->
        <?php if (sidebarRoleAllowed(['administrator', 'farm_manager'])) { ?>

            <li>
                <a href="reports.php">
                    <i class="fas fa-chart-line"></i>
                    Reports & Analytics
                </a>
            </li>

        <?php } ?>


        <!-- AI ASSISTANT: ADMIN, MANAGER -->
        <?php if (sidebarRoleAllowed(['administrator', 'farm_manager'])) { ?>

            <li>
                <a href="ai_assistant.php">
                    <i class="fas fa-robot"></i>
                    AI Assistant
                </a>
            </li>

        <?php } ?>


        <!-- USERS: ADMIN ONLY -->
        <?php if (sidebarRoleAllowed(['administrator'])) { ?>

            <li>
                <a href="register.php">
                    <i class="fas fa-user-plus"></i>
                    Register User
                </a>
            </li>

        <?php } ?>

    </ul>


    <div class="sidebar-footer">

        <a href="logout.php">
            <i class="fas fa-sign-out-alt"></i>
            Logout
        </a>

    </div>

</div>