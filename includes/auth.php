<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ============================================================
   CHECK LOGIN
============================================================ */

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}


/* ============================================================
   ROLE ACCESS CONTROL
============================================================ */

function allowRoles($allowedRoles)
{
    if (!isset($_SESSION['role'])) {
        header("Location: unauthorized.php");
        exit();
    }

    $userRole = strtolower(trim($_SESSION['role']));

    $allowedRoles = array_map(function ($role) {
        return strtolower(trim($role));
    }, $allowedRoles);

    if (!in_array($userRole, $allowedRoles)) {
        header("Location: unauthorized.php");
        exit();
    }
}


/* ============================================================
   CHECK ROLE FOR SIDEBAR LINKS
============================================================ */

function hasRole($roles)
{
    if (!isset($_SESSION['role'])) {
        return false;
    }

    $userRole = strtolower(trim($_SESSION['role']));

    $allowedRoles = array_map(function ($role) {
        return strtolower(trim($role));
    }, $roles);

    return in_array($userRole, $allowedRoles);
}