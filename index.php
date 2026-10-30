<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';
startSecureSession();
redirect(isLoggedIn() ? 'dashboard/' : 'auth/login.php');
