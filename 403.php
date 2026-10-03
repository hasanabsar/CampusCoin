<?php
require_once __DIR__ . '/includes/auth.php';
renderErrorPage(403, 'Access denied', 'You do not have permission to view this page.', 'lock');
