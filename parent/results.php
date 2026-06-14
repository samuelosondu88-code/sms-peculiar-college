<?php
require_once __DIR__ . '/../config/session.php';
requireRole('parent');
redirect('/parent/results/index.php');
