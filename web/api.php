<?php

session_cache_limiter(false);

require_once '../vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$log = new Logger('main');
$log->pushHandler(new StreamHandler('../everything.log', Logger::DEBUG));
$log->pushHandler(new StreamHandler('../errors.log', Logger::ERROR));

DB::$dbName = 'uploader';
DB::$user = 'uploader';
DB::$password = '8F3szdkzaEpyywjW';
DB::$port = 3306;

DB::$error_handler = 'sql_error_handler';
DB::$nonsql_error_handler = 'nonsql_error_handler';

function nonsql_error_handler($params) {
    global $app, $log;
    $log->error(" Database error: " . $params['error']);
    http_response_code(500);
    echo '"500 - Internal error"';
    die;
}

function sql_error_handler($params) {
    global $app, $log;
    $log->error(" SQL error: " . $params['error']);
    $log->error(" in query: " . $params['query']);
    http_response_code(500);
    echo '"500 - Internal error"';
    die;
}

$app = new \Slim\Slim();
//Allow integers only for retrieving functions
\Slim\Route::setDefaultConditions(array(
    'id' => '\d+'
));

$app->response->headers->set('content-type', 'application/json');

//This function would authorize the user using the token provided on the calls.
function authUser()
{
    global $app;
    $token = $app->request->headers->get("PHP_AUTHORIZATION");
    
}

//-------------------------------------------MAIN------------------------------------------------------------------------------------

$app->get('/', function() use($app) {
    $app->response->headers->set('content-type', 'text/html');
    echo file_get_contents('upload.html');
});

//-------------------------------------------UPLOAD------------------------------------------------------------------------------------
//Function will save the file in the database as binary in a mediumblob field.
$app->post('/api/upload', function() use($app, $log) {
    $userID = 1;
    /*if (!authUser()) {  //This function verifies the given token with the one stored in the DB.
        return;
    }*/
    if (isset($_POST['file'])) {
        $attachment = $_POST['file'];
        list($type, $attachment) = explode(';', $attachment);
        list(, $attachment)      = explode(',', $attachment);
        $blob = base64_decode($attachment);
        //$blob = $data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $attachment));
        $imagePath='';
        $attachmentMimeType = $_POST['attachmentMimeType'];
        $attachmentFileName = $_POST['attachmentFileName'];
        DB::insert('files', array(
            'userID' => $userID,
            'attachment' => $blob,
            'attachmentMimeType' => $attachmentMimeType,
            'attachmentFileName' => $attachmentFileName,
            'imagePath' => $imagePath
        ));
    }
    echo json_encode(true);
});
//Function will save the file in the database as a file in the /uploads/ folder.
$app->post('/api/upload2', function() use($app, $log) {
    $userID = 1;
    /*if (!authUser()) {  //This function verifies the given token with the one stored in the DB.
        return;
    }*/
    if (isset($_POST['file'])) {
        $attachment = $_POST['file'];
        list($type, $attachment) = explode(';', $attachment);
        list(, $attachment)      = explode(',', $attachment);
        $blob = base64_decode($attachment);
        $attachmentMimeType = $_POST['attachmentMimeType'];
        $attachmentFileName = $_POST['attachmentFileName'];
        $pdf = fopen ('uploads/'. $attachmentFileName,'w');
        fwrite ($pdf,$blob);
        fclose ($pdf);
        $imagePath='uploads/' . $attachmentFileName;
        
        DB::insert('files', array(
            'userID' => $userID,
            'attachmentMimeType' => $attachmentMimeType,
            'attachmentFileName' => $attachmentFileName,
            'imagePath' => $imagePath
        ));
    }
    echo json_encode(true);
});
//Made this function to retrieve the file from the database.
$app->get('/api/upload/:id', function($fileID = 0) use($log) {
    $file = DB::queryFirstRow('SELECT attachment, attachmentMimeType, attachmentFileName FROM files WHERE ID=%i', $fileID);
    //$log->debug($file['attachmentFileName']);
    header("Content-length: " . strlen($file['attachment']));
    header("Content-Type: " . $file['attachmentMimeType']);
    header("Content-Disposition: attachment; filename=\"".$file['attachmentFileName']."\"");
    echo $file['attachment'];
});
//Made this function to retrieve the file from the /uploads/ folder.
$app->get('/api/upload2/:id', function($fileID = 0) use($log) {
    $file = DB::queryFirstRow('SELECT attachmentMimeType, attachmentFileName, imagePath FROM files WHERE ID=%i', $fileID);
    
    header("Content-Type: application/force-download");
    header("Content-Disposition: attachment; filename=\"".$file['attachmentFileName']."\"");
    header ('Cache-Control: cache, must-revalidate');
    header ('Pragma: public');
    //$log->debug($fileurl);
    readfile($file['imagePath']);
});

//-------------------------------------------UPLOAD-ANGULAR------------------------------------------------------------------------------------
//This is an alternate version receiving formData from a http request using Angular.js
$app->post('/api/upload/angular', function() use($app, $log) {
    $userID = 1;
    /*if (!authUser()) {  //This function verifies the given token with the one stored in the DB.
        return;
    }*/
    $request = json_decode(file_get_contents("php://input"));
    $file = $request->file;

    if (isset($file)) {
        $attachment = $file;
        list($type, $attachment) = explode(';', $attachment);
        list(, $attachment)      = explode(',', $attachment);
        $blob = base64_decode($attachment);
        $attachmentFileName = $request->attachmentFileName;
        $attachmentMimeType = $request->attachmentMimeType;
            $pdf = fopen ('uploads/'. $attachmentFileName,'w');
            fwrite ($pdf,$blob);
            fclose ($pdf);
        $imagePath='uploads/' . $attachmentFileName;
        
        DB::insert('files', array(
            'userID' => $userID,
            'attachmentMimeType' => $attachmentMimeType,
            'attachmentFileName' => $attachmentFileName,
            'imagePath' => $imagePath
        ));
    }
    echo json_encode(true);
});

$app->run();

