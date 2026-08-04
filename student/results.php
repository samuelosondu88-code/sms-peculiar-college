<?php
require_once __DIR__ . '/../config/session.php';
requireRole('student');
require_once __DIR__ . '/../includes/functions.php';
redirect('/student/results/index.php');
