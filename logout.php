<?php
/** Securely end the user session. */
require_once __DIR__ . '/includes/auth.php';
logout_user();
session_start();                 // fresh session so we can show a flash
set_flash('info', 'You have been logged out.');
redirect('index.php');
