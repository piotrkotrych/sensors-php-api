<?php
//dotenv load
require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

error_reporting(E_ALL);
ini_set('display_errors', 'On');

//headers to bypass cors
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

//database connection oop way
$servername = $_ENV['DB_HOST'];
$username = $_ENV['DB_USER'];
$password = $_ENV['DB_PASS'];
$dbname = $_ENV['DB_NAME'];

$conn = new mysqli($servername, $username, $password, $dbname);

//set charset to utf8
$conn->set_charset("utf8");

//set mysql timezone to berlin
$conn->query("SET time_zone = '+02:00'");

//check connection
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

//header for json content
header('Content-Type: application/json');

//switch case for type of request
switch ($_GET['action']) {
  case 'insertData':
    //get post data
    $data = json_decode(file_get_contents("php://input"));


    //begin transaction
    $conn->begin_transaction();

    //insert data oop way using prepared statements
    $stmt = $conn->prepare("INSERT INTO `sensor_data` (`chipid`, `temperature`, `humidity`, `pressure`) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iddd", $data->chipid, $data->temperature, $data->humidity, $data->pressure);


    if ($stmt->execute()) {
      //commit transaction
      $conn->commit();
      echo json_encode(array('success' => true, 'message' => 'Data inserted', 'data' => $data));
    } else {
      //get error 
      $error = $stmt->error;
      //rollback transaction
      $conn->rollback();
      echo json_encode(array('success' => false, 'message' => 'Data not inserted', 'error' => $error));
    }

    $stmt->close();
    exit();
    break;

  case 'createSensor':
    //get post data
    $data = json_decode(file_get_contents("php://input"));

    //begin transaction
    $conn->begin_transaction();

    //insert data oop way using prepared statements
    $stmt = $conn->prepare("INSERT INTO `sensor_info` (`chipid`, `location`) VALUES (?, ?)");
    $stmt->bind_param("is", $data->chipid, $data->location);

    if ($stmt->execute()) {
      //commit transaction
      $conn->commit();
      echo json_encode(array('success' => true, 'message' => 'Sensor created', 'data' => $data));
    } else {
      //get error
      $error = $stmt->error;
      //rollback transaction
      $conn->rollback();
      echo json_encode(array('success' => false, 'message' => 'Sensor not created', 'error' => $error));
    }

    $stmt->close();
    exit();
    break;

  case 'getSensorData':
    //get chip id from GET
    $chipid = $_GET['chipid'];

    $sensor = array();

    //get sensor info from database using prepared statements
    $stmt = $conn->prepare("SELECT chipid, location, createdAt, updatedAt FROM `sensor_info` WHERE `chipid` = ? ORDER BY `createdAt` DESC LIMIT 1");
    $stmt->bind_param("i", $chipid);
    $stmt->execute();

    //bind result set columns to variables
    $stmt->bind_result($chipid, $location, $createdAt, $updatedAt);

    //get error
    $error = $stmt->error;

    //get result
    while ($stmt->fetch()) {
      $sensor = array('chipid' => $chipid, 'location' => $location, 'createdAt' => $createdAt, 'updatedAt' => $updatedAt);
    }

    //echo error if exists
    if ($error) {
      echo json_encode(array('success' => false, 'message' => 'Error', 'error' => $error));
    }

    //echo result if exists
    if (!empty($sensor)) {
      //add new key where we will store data and name the key like $location
      $sensor['data'] = array();

      //get sensor data from database using prepared statements
      $stmt = $conn->prepare("SELECT temperature, humidity, pressure, createdAt FROM `sensor_data` WHERE `chipid` = ? ORDER BY `createdAt` DESC LIMIT 1000");
      $stmt->bind_param("i", $chipid);
      $stmt->execute();

      //bind result set columns to variables
      $stmt->bind_result($temperature, $humidity, $pressure, $createdAt);

      //get error
      $error = $stmt->error;

      //get result
      while ($stmt->fetch()) {
        //add data to array
        array_push($sensor['data'], array('temperature' => $temperature, 'humidity' => $humidity, 'pressure' => $pressure, 'createdAt' => $createdAt));
      }

      //echo error if exists
      if ($error) {
        echo json_encode(array('success' => false, 'message' => 'Error', 'error' => $error));
      }

      //echo result if exists
      if (!empty($sensor)) {
        echo json_encode(array('success' => true, 'message' => 'Sensor found', 'sensor' => $sensor));
      } else {
        echo json_encode(array('success' => false, 'message' => 'Sensor not found'));
      }
    } else {
      echo json_encode(array('success' => false, 'message' => 'Sensor not found'));
    }

    $stmt->close();
    exit();

    break;

    //same as getSensorData but data is between two dates
  case 'getSensorDataByDates':
    //get chip id from GET
    $chipid = $_GET['chipid'];
    $from = $_GET['from'];
    $to = $_GET['to'];

    $sensor = array();

    //get sensor info from database using prepared statements
    $stmt = $conn->prepare("SELECT chipid, location, createdAt, updatedAt FROM `sensor_info` WHERE `chipid` = ? ORDER BY `createdAt` DESC LIMIT 1");
    $stmt->bind_param("i", $chipid);
    $stmt->execute();

    //bind result set columns to variables
    $stmt->bind_result($chipid, $location, $createdAt, $updatedAt);

    //get error
    $error = $stmt->error;

    //get result
    while ($stmt->fetch()) {
      $sensor = array('chipid' => $chipid, 'location' => $location, 'createdAt' => $createdAt, 'updatedAt' => $updatedAt);
    }

    //echo error if exists
    if ($error) {
      echo json_encode(array('success' => false, 'message' => 'Error', 'error' => $error));
    }

    //echo result if exists
    if (!empty($sensor)) {
      //add new key where we will store data and name the key like $location
      $sensor['data'] = array();

      //get sensor data from database using prepared statements
      $stmt = $conn->prepare("SELECT temperature, humidity, pressure, createdAt FROM `sensor_data` WHERE `chipid` = ? AND date(`createdAt`) BETWEEN ? AND ? ORDER BY `createdAt` DESC");
      $stmt->bind_param("iss", $chipid, $from, $to);
      $stmt->execute();

      //bind result set columns to variables
      $stmt->bind_result($temperature, $humidity, $pressure, $createdAt);

      //get error
      $error = $stmt->error;

      //get result
      while ($stmt->fetch()) {
        //add data to array
        array_push(
          $sensor['data'],
          array('temperature' => $temperature, 'humidity' => $humidity, 'pressure' => $pressure, 'createdAt' => $createdAt)
        );
      }

      //echo error if exists
      if ($error) {
        echo json_encode(array('success' => false, 'message' => 'Error', 'error' => $error));
      }

      //echo result if exists
      if (!empty($sensor)) {
        echo json_encode(array('success' => true, 'message' => 'Sensor found', 'sensor' => $sensor));
      } else {
        echo json_encode(array('success' => false, 'message' => 'Sensor not found'));
      }
    } else {
      echo json_encode(array('success' => false, 'message' => 'Sensor not found'));
    }

    $stmt->close();
    exit();

    break;

  case 'getSensorsData':
    $sensors = array();

    //get sensor info from database using prepared statements
    $stmt = $conn->prepare("SELECT chipid, location, createdAt, updatedAt FROM `sensor_info` ORDER BY `createdAt` DESC");
    $stmt->execute();

    //bind result set columns to variables
    $stmt->bind_result($chipid, $location, $createdAt, $updatedAt);

    //get error
    $error = $stmt->error;

    //get result
    while ($stmt->fetch()) {
      array_push($sensors, array('chipid' => $chipid, 'location' => $location, 'createdAt' => $createdAt, 'updatedAt' => $updatedAt));
    }

    //echo error if exists
    if ($error) {
      echo json_encode(array('success' => false, 'message' => 'Error', 'error' => $error));
    }

    //echo result if exists
    if (!empty($sensors)) {
      //assign data to sensors
      foreach ($sensors as $key => $sensor) {
        //add new key where we will store data and name the key like $location
        $sensors[$key]['data'] = array();

        //get sensor data from database using prepared statements
        $stmt = $conn->prepare("SELECT temperature, humidity, pressure, createdAt FROM `sensor_data` WHERE `chipid` = ? ORDER BY `createdAt` DESC LIMIT 1000");
        $stmt->bind_param("i", $sensor['chipid']);
        $stmt->execute();

        //bind result set columns to variables
        $stmt->bind_result($temperature, $humidity, $pressure, $createdAt);

        //get error
        $error = $stmt->error;

        //get result
        while ($stmt->fetch()) {
          //add data to array
          array_push($sensors[$key]['data'], array('temperature' => $temperature, 'humidity' => $humidity, 'pressure' => $pressure, 'createdAt' => $createdAt));
        }

        //echo error if exists
        if ($error) {
          echo json_encode(array('success' => false, 'message' => 'Error', 'error' => $error));
        }
      }
      echo json_encode(array('success' => true, 'message' => 'Sensors found', 'qty' => count($sensors), 'sensors' => $sensors));
    } else {
      echo json_encode(array('success' => false, 'message' => 'Sensors not found'));
    }

    $stmt->close();
    exit();

    break;
  case 'getSensorsDataLatest':
    $sensors = array();

    //get sensor info from database using prepared statements
    $stmt = $conn->prepare("SELECT chipid, location, createdAt, updatedAt FROM `sensor_info` ORDER BY `createdAt` DESC");
    $stmt->execute();

    //bind result set columns to variables
    $stmt->bind_result($chipid, $location, $createdAt, $updatedAt);

    //get error
    $error = $stmt->error;

    //get result
    while ($stmt->fetch()) {
      array_push($sensors, array('chipid' => $chipid, 'location' => $location, 'createdAt' => $createdAt, 'updatedAt' => $updatedAt));
    }

    //echo error if exists
    if ($error) {
      echo json_encode(array('success' => false, 'message' => 'Error', 'error' => $error));
    }

    //echo result if exists
    if (!empty($sensors)) {
      //assign data to sensors
      foreach ($sensors as $key => $sensor) {
        //add new key where we will store data and name the key like $location
        $sensors[$key]['data'] = array();

        //get sensor data from database using prepared statements
        $stmt = $conn->prepare("SELECT temperature, humidity, pressure, createdAt FROM `sensor_data` WHERE `chipid` = ? ORDER BY `createdAt` DESC LIMIT 1");
        $stmt->bind_param("i", $sensor['chipid']);
        $stmt->execute();

        //bind result set columns to variables
        $stmt->bind_result($temperature, $humidity, $pressure, $createdAt);

        //get error
        $error = $stmt->error;

        //get result
        while ($stmt->fetch()) {
          //add data to array
          array_push($sensors[$key]['data'], array('temperature' => $temperature, 'humidity' => $humidity, 'pressure' => $pressure, 'createdAt' => $createdAt));
        }

        //echo error if exists
        if ($error) {
          echo json_encode(array('success' => false, 'message' => 'Error', 'error' => $error));
        }
      }
      echo json_encode(array('success' => true, 'message' => 'Sensors found', 'qty' => count($sensors), 'sensors' => $sensors));
    } else {
      echo json_encode(array('success' => false, 'message' => 'Sensors not found'));
    }

    $stmt->close();
    exit();

    break;
  case 'getSensorsDataByDates':
    $sensors = array();

    $from = $_GET['from'];
    $to = $_GET['to'];

    //get sensor info from database using prepared statements
    $stmt = $conn->prepare("SELECT chipid, location, createdAt, updatedAt FROM `sensor_info` ORDER BY `createdAt` DESC");
    $stmt->execute();

    //bind result set columns to variables
    $stmt->bind_result($chipid, $location, $createdAt, $updatedAt);

    //get error
    $error = $stmt->error;

    //get result
    while ($stmt->fetch()) {
      array_push($sensors, array('chipid' => $chipid, 'location' => $location, 'createdAt' => $createdAt, 'updatedAt' => $updatedAt));
    }

    //echo error if exists
    if ($error) {
      echo json_encode(array('success' => false, 'message' => 'Error', 'error' => $error));
    }

    //echo result if exists
    if (!empty($sensors)) {
      //assign data to sensors
      foreach ($sensors as $key => $sensor) {
        //add new key where we will store data and name the key like $location
        $sensors[$key]['data'] = array();

        //get sensor data from database using prepared statements
        $stmt = $conn->prepare("SELECT temperature, humidity, pressure, createdAt FROM `sensor_data` WHERE `chipid` = ? AND date(createdAt) BETWEEN ? AND ? ORDER BY `createdAt` DESC");
        $stmt->bind_param("iss", $sensor['chipid'], $from, $to);
        $stmt->execute();

        //bind result set columns to variables
        $stmt->bind_result($temperature, $humidity, $pressure, $createdAt);

        //get error
        $error = $stmt->error;

        //get result
        while ($stmt->fetch()) {
          //add data to array
          array_push($sensors[$key]['data'], array('temperature' => $temperature, 'humidity' => $humidity, 'pressure' => $pressure, 'createdAt' => $createdAt));
        }

        //echo error if exists
        if ($error) {
          echo json_encode(array('success' => false, 'message' => 'Error', 'error' => $error));
        }
      }
      echo json_encode(array('success' => true, 'message' => 'Sensors found', 'qty' => count($sensors), 'sensors' => $sensors));
    } else {
      echo json_encode(array('success' => false, 'message' => 'Sensors not found'));
    }

    $stmt->close();
    exit();

    break;

  case 'grafana':
    $sensors = array();
    //geta all info from database using prepared statements and left join data, max rows 5000
    $stmt = $conn->prepare("SELECT sensor_info.chipid, sensor_info.location, sensor_data.temperature, sensor_data.humidity, sensor_data.pressure, sensor_data.createdAt FROM `sensor_info` LEFT JOIN `sensor_data` ON sensor_info.chipid = sensor_data.chipid ORDER BY sensor_data.createdAt DESC LIMIT 5000");
    $stmt->execute();

    //bind result set columns to variables
    $stmt->bind_result($chipid, $location, $temperature, $humidity, $pressure, $createdAt);

    //get error
    $error = $stmt->error;

    //get result
    while ($stmt->fetch()) {
      array_push($sensors, array('chipid' => $chipid, 'location' => $location, 'temperature' => $temperature, 'humidity' => $humidity, 'pressure' => $pressure, 'createdAt' => $createdAt));
    }

    //echo error if exists
    if ($error) {
      echo json_encode(array('success' => false, 'message' => 'Error', 'error' => $error));
    }

    //echo result if exists
    if (!empty($sensors)) {
      echo json_encode(array('success' => true, 'message' => 'Sensors found', 'qty' => count($sensors), 'sensors' => $sensors));
    } else {
      echo json_encode(array('success' => false, 'message' => 'Sensors not found'));
    }
  default:
    echo "Wrong action";
    break;
}
