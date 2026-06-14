<?php
require_once __DIR__ . '/../config/session.php';
requireRole('teacher');
redirect('/teacher/results/index.php');
