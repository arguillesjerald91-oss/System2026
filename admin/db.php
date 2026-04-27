<?php
// This file intentionally proxies to the central db.php to avoid duplicate Database class definitions.
// Include the single source of truth for the Database class.
include_once __DIR__ . '/../db.php';

// Nothing else here. The Database class is declared in the project's root db.php (guarded by class_exists).
?>