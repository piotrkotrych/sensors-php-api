<?php
//dotenv load
require_once __DIR__ . '/vendor/autoload.php';
//require utils.php
require_once __DIR__ . '/utils.php';
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
      //status 200
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

    //check if chipid is set
    $chipid = get_chipid($_GET['chipid']);

    //create empty array
    $sensor = array();

    //get sensor info from database using prepared statements
    $stmt = $conn->prepare("SELECT chipid, location, createdAt, updatedAt, (SELECT TIMESTAMPDIFF(SECOND, MAX(createdAt), NOW()) < 3600 FROM sensor_data WHERE chipid = ?) as is_recent FROM `sensor_info` WHERE `chipid` = ? ORDER BY `createdAt` DESC LIMIT 1");
    $stmt->bind_param("ii", $chipid, $chipid);
    $stmt->execute();

    //bind result set columns to variables
    $stmt->bind_result($chipid, $location, $createdAt, $updatedAt, $is_recent);

    //get error
    $error = $stmt->error;

    //get result
    while ($stmt->fetch()) {
      $is_recent = $is_recent ? true : false;
      //add data to array
      $sensor = array('chipid' => $chipid, 'location' => $location, 'createdAt' => $createdAt, 'updatedAt' => $updatedAt, 'is_recent' => $is_recent);
    }

    //echo error if exists
    if ($error) {
      send_response(500, false, 'Error', array('error' => $error));
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
        send_response(500, false, 'Error', array('error' => $error));
      }

      //echo result if exists
      if (!empty($sensor)) {
        send_response(200, true, 'Sensor data', $sensor);
      } else {
        send_response(404, false, 'Sensor data not found');
      }
    } else {
      send_response(404, false, 'Sensor not found');
    }

    $stmt->close();
    exit();

    break;

    //same as getSensorData but data is between two dates
  case 'getSensorDataByDates':

    //check if chipid, from, to is set
    $chipid = get_chipid($_GET['chipid']);

    //check if from is set and is valid date
    $from = get_date($_GET['from']);

    //same for to
    $to = get_date($_GET['to']);

    //create empty array
    $sensor = array();

    //get sensor info from database using prepared statements
    $stmt = $conn->prepare("SELECT chipid, location, createdAt, updatedAt, (SELECT TIMESTAMPDIFF(SECOND, MAX(createdAt), NOW()) < 3600 FROM sensor_data WHERE chipid = ?) as is_recent FROM `sensor_info` WHERE `chipid` = ? ORDER BY `createdAt` DESC LIMIT 1");
    $stmt->bind_param("ii", $chipid, $chipid);
    $stmt->execute();

    //bind result set columns to variables
    $stmt->bind_result($chipid, $location, $createdAt, $updatedAt, $is_recent);

    //get error
    $error = $stmt->error;

    //get result
    while ($stmt->fetch()) {
      $is_recent = $is_recent ? true : false;
      $sensor = array('chipid' => $chipid, 'location' => $location, 'createdAt' => $createdAt, 'updatedAt' => $updatedAt, 'is_recent' => $is_recent);
    }

    //echo error if exists
    if ($error) {
      send_response(500, false, 'Error', array('error' => $error));
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
        send_response(500, false, 'Error', array('error' => $error));
      }

      //echo result if exists
      if (!empty($sensor)) {
        send_response(200, true, 'Sensor data', $sensor);
      } else {
        send_response(404, false, 'Sensor data not found');
      }
    } else {
      send_response(404, false, 'Sensor not found');
    }

    $stmt->close();
    exit();

    break;

  case 'getSensorsData':
    $sensors = array();

    //get sensor info from database using prepared statements
    $stmt = $conn->prepare("SELECT chipid, location, createdAt, updatedAt, (SELECT TIMESTAMPDIFF(SECOND, MAX(createdAt), NOW()) < 3600 FROM sensor_data as sd WHERE sd.chipid = sensor_info.chipid) as is_recent FROM `sensor_info` ORDER BY `createdAt` DESC");
    $stmt->execute();

    //bind result set columns to variables
    $stmt->bind_result($chipid, $location, $createdAt, $updatedAt, $is_recent);

    //get error
    $error = $stmt->error;

    //get result
    while ($stmt->fetch()) {
      $is_recent = $is_recent ? true : false;
      array_push($sensors, array('chipid' => $chipid, 'location' => $location, 'createdAt' => $createdAt, 'updatedAt' => $updatedAt, 'is_recent' => $is_recent));
    }

    //echo error if exists
    if ($error) {
      send_response(500, false, 'Error', array('error' => $error));
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
          $is_recent = $is_recent ? true : false;
          //add data to array
          array_push($sensors[$key]['data'], array('temperature' => $temperature, 'humidity' => $humidity, 'pressure' => $pressure, 'createdAt' => $createdAt));
        }

        //echo error if exists
        if ($error) {
          send_response(500, false, 'Error', array('error' => $error));
        }
      }
      send_response(200, true, 'Sensor data', $sensors);
    } else {
      send_response(404, false, 'Sensor data not found');
    }

    $stmt->close();
    exit();

    break;
  case 'getSensorsDataLatest':
    $sensors = array();

    //get sensor info from database using prepared statements
    $stmt = $conn->prepare("SELECT chipid, location, createdAt, updatedAt, (SELECT TIMESTAMPDIFF(SECOND, MAX(createdAt), NOW()) < 3600 FROM sensor_data as sd WHERE sd.chipid = sensor_info.chipid) as is_recent FROM `sensor_info` ORDER BY `createdAt` DESC");
    $stmt->execute();

    //bind result set columns to variables
    $stmt->bind_result($chipid, $location, $createdAt, $updatedAt, $is_recent);

    //get error
    $error = $stmt->error;

    //get result
    while ($stmt->fetch()) {
      $is_recent = $is_recent ? true : false;
      array_push($sensors, array('chipid' => $chipid, 'location' => $location, 'createdAt' => $createdAt, 'updatedAt' => $updatedAt, 'is_recent' => $is_recent));
    }

    //echo error if exists
    if ($error) {
      send_response(500, false, 'Error', array('error' => $error));
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
          send_response(500, false, 'Error', array('error' => $error));
        }
      }
      send_response(200, true, 'Sensor data', $sensors);
    } else {
      send_response(404, false, 'Sensor data not found');
    }

    $stmt->close();
    exit();

    break;
  case 'getSensorsDataByDates':
    $sensors = array();

    $from = get_date($_GET['from']);
    $to = get_date($_GET['to']);

    //get sensor info from database using prepared statements
    $stmt = $conn->prepare("SELECT chipid, location, createdAt, updatedAt, (SELECT TIMESTAMPDIFF(SECOND, MAX(createdAt), NOW()) < 3600 FROM sensor_data as sd WHERE sd.chipid = sensor_info.chipid) as is_recent FROM `sensor_info` ORDER BY `createdAt` DESC");
    $stmt->execute();

    //bind result set columns to variables
    $stmt->bind_result($chipid, $location, $createdAt, $updatedAt, $is_recent);

    //get error
    $error = $stmt->error;

    //get result
    while ($stmt->fetch()) {
      $is_recent = $is_recent ? true : false;

      array_push($sensors, array('chipid' => $chipid, 'location' => $location, 'createdAt' => $createdAt, 'updatedAt' => $updatedAt, 'is_recent' => $is_recent));
    }

    //echo error if exists
    if ($error) {
      send_response(500, false, 'Error', array('error' => $error));
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
          send_response(500, false, 'Error', array('error' => $error));
        }
      }
      send_response(200, true, 'Sensor data', $sensors);
    } else {
      send_response(404, false, 'Sensor data not found');
    }

    $stmt->close();
    exit();

    break;

  case 'grafana':

    $limit = intval($_GET['limit']);

    $sensors = array();
    //geta all info from database using prepared statements 
    $stmt = $conn->prepare("SELECT location FROM `sensor_info` ORDER BY `createdAt` DESC");
    $stmt->execute();

    //bind result set columns to variables
    $stmt->bind_result($location);

    //get error
    $error = $stmt->error;

    //get result
    while ($stmt->fetch()) {
      // add location to array as a key
      $sensors[$location] = array();
    }

    //echo error if exists
    if ($error) {
      echo json_encode(array('success' => false, 'message' => 'Error', 'error' => $error));
    }

    //for each sensors[key] as $key, add data to array from database sensor_data table and left join sensor_info table
    foreach ($sensors as $key => $sensor) {
      //get sensor data from database using prepared statements
      $stmt = $conn->prepare("SELECT location, temperature, humidity, pressure, sensor_data.createdAt, CASE
    WHEN TIMESTAMPDIFF(HOUR, sensor_data.createdAt, NOW()) < 1 THEN 'true' ELSE 'false' END as is_recent 
    FROM `sensor_data` LEFT JOIN `sensor_info` ON sensor_data.chipid = sensor_info.chipid WHERE sensor_info.location = ? ORDER BY sensor_data.createdAt DESC LIMIT ?");
      $stmt->bind_param("si", $key, $limit);
      $stmt->execute();

      //bind result set columns to variables
      $stmt->bind_result($location, $temperature, $humidity, $pressure, $createdAt, $is_recent);

      //get error
      $error = $stmt->error;

      //get result
      while ($stmt->fetch()) {
        $is_recent = ($is_recent == 'true');
        //add data to array
        array_push($sensors[$key], array('location' => $location, 'temperature' => $temperature, 'humidity' => $humidity, 'pressure' => $pressure, 'createdAt' => $createdAt, 'is_recent' => $is_recent));
      }

      //echo error if exists
      if ($error) {
        echo json_encode(array('success' => false, 'message' => 'Error', 'error' => $error));
      }
    }


    //echo result if exists
    if (!empty($sensors)) {
      echo json_encode(array('success' => true, 'message' => 'Sensors found', 'qty' => count($sensors), 'sensors' => $sensors));
    } else {
      echo json_encode(array('success' => false, 'message' => 'Sensors not found'));
    }

    $stmt->close();
    exit();

    break;
  default:
    echo "Wrong action";
    break;
}