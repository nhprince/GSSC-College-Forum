<?php
declare(strict_types=1);
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
initSession();
requireLogin();
validateCsrf();
logout();
