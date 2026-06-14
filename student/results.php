<?php
require_once __DIR__ . '/../config/session.php';
requireRole('student');
redirect('/student/results/index.php');
