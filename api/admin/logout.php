<?php
require_once __DIR__ . '/../../backend/bootstrap.php';
session_destroy();
json_response(['success'=>true,'message'=>'Logged out.']);
