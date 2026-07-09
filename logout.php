<?php
require_once 'config/supabase.php';
session_destroy();
header('Location: /index.php');
exit;
