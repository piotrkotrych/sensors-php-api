<?php

function get_chipid($chipid)
{
  if (!isset($chipid)) {
    http_response_code(400);
    echo json_encode(array('success' => false, 'message' => 'Chipid not set'));
    exit();
  } else if (!is_numeric($chipid)) {
    http_response_code(400);
    echo json_encode(array('success' => false, 'message' => 'Chipid must be a number'));
    exit();
  } else {
    return $chipid;
  }
}

function get_date($date)
{
  if (!isset($date)) {
    http_response_code(400);
    echo json_encode(array('success' => false, 'message' => 'Date not set'));
    exit();
  } else if (!DateTime::createFromFormat('Y-m-d', $date)) {
    http_response_code(400);
    echo json_encode(array('success' => false, 'message' => 'Invalid date format'));
    exit();
  } else {
    return $date;
  }
}

function send_response($http_code, $success, $message, $data = null)
{
  http_response_code($http_code);
  echo json_encode(array('success' => $success, 'message' => $message, 'data' => $data));
  exit();
}