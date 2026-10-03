<?php
require_once __DIR__ . '/includes/auth.php';
renderErrorPage(404, 'Page not found', 'The page you are looking for does not exist or has been moved.', 'search');
