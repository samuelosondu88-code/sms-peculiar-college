<?php
require_once __DIR__ . '/../config/session.php';
requireRole('teacher');
require_once __DIR__ . '/../includes/functions.php';
redirect('/teacher/results/index.php');
