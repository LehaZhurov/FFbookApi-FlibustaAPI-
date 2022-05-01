<?
// 
// ini_set('error_reporting', E_ALL);
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// 
header('Access-Control-Allow-Origin:*');
// 
$root = dirname(__FILE__);
$request = $_SERVER['REQUEST_URI'];
$filename = basename($request);
$path = $root.'/'.$request;
if (file_exists($path)) {
    if (ob_get_level()) {
        ob_end_clean();
    }
    header("Content-Type: application/pdf; charset=UTF-8");
    header("Content-Length: ".filesize($path));
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    header("Content-Transfer-Encoding: binary");
    header("Cache-Control: must-revalidate");
    header("Pragma: no-cache");
    header("Expires: 0");
    readfile($path);
}
// 
require "Lib/Litero/Router.php";
require "Lib/FS/FlibustaScrabber.php";
// 
$router = Bit55\Litero\Router::fromGlobals();



$router->add ('/get_book_genre/:any', function($code){
    $flib = New FlibustaScrabber();
    $data = $flib->GetBookGenre($code);  
    if(empty($data)){
        echo json_encode(['error' => 'Not found']);
    }else{
        echo json_encode($data);
    }
    // echo 'http://www.flibusta.site/b/'.$code.'/fb2';
});

$router->add ('/get_genre', function(){
    $flib = New FlibustaScrabber();
    echo json_encode($flib->GetListGenre());
});

$router->add ( '/get_new_book', function(){
    $flib = New FlibustaScrabber();
    echo json_encode($flib->GetNewBook());
});
$router->add ( '/get_book_info/:any', function($code){
    $flib = New FlibustaScrabber();
    $data = $flib->GetBookInfo($code);
    if(empty($data)){
        echo json_encode(['error' => 'Not found']);
    }else{
        echo json_encode($data);
    }
});
$router->add ( '/get_popular_book/:any', function($page){
    $flib = New FlibustaScrabber();
    $data = $flib->GetPopularBook($page);
    if(empty($data)){
        header('HTTP/1.0 404 not found');
        echo json_encode(['error' => 'Not found']);
    }else{
        echo json_encode($data);
    }
});
$router->add ( '/search_book/:any/:any', function($query,$page){
    $flib = New FlibustaScrabber();
    $data = $flib->SearchFromBook($query,$page);
    if(empty($data)){
        echo json_encode(['error' => 'Not found']);
        header('HTTP/1.0 404 not found');
    }else{
        echo json_encode($data);
    }
});
$router->add ( '/search_author/:any/:any', function($query,$page){
    $flib = New FlibustaScrabber();
    $data = $flib->SearchFromAuthor($query,$page);
    if(empty($data)){
        echo json_encode(['error' => 'Not found']);
        header('HTTP/1.0 404 not found');
    }else{
        echo json_encode($data);
    }
});
$router->add ( '/search_serial/:any/:any', function($query,$page){
    $flib = New FlibustaScrabber();
    $data = $flib->SearchFromSerial($query,$page);
    if(empty($data)){
        header('HTTP/1.0 404 not found');
        echo json_encode(['error' => 'Not found']);
    }else{
        echo json_encode($data);
    }
});

$router->add ( '/search/:any/:any', function($query,$page){
    $flib = New FlibustaScrabber();
    $data = $flib->Search($query,$page);
    if(empty($data['book']) && empty($data['author']) && empty($data['serial'])){
        header('HTTP/1.0 404 not found');
        echo json_encode(['error' => 'Not found']);
    }else{
        echo json_encode($data);
    }
});

$router->add ( '/get_book_author/:any', function($code){
    $flib = New FlibustaScrabber();
    $data = $flib->GetBookFormAuthor($code);
    if(empty($data)){
        echo json_encode(['error' => 'Not found']);
    }else{
        echo json_encode($data);
    }
});
$router->add ( '/get_book_serial/:any/:any', function($code,$page){
    $flib = New FlibustaScrabber();
    $data = $flib->GetBookFromSerial($code,$page);
    if(empty($data)){
        echo json_encode(['error' => 'Not found']);
    }else{
        echo json_encode($data);
    }
});
$router->add ( '/download_book/:any', function($code){
    $flib = New FlibustaScrabber();
    $data = $flib->Download($code,'book',"http://flibapi.tmweb.ru");  
    if(empty($data)){
        echo json_encode(['error' => 'Not found']);
    }else{
        echo $data;
    }
    // echo 'http://www.flibusta.site/b/'.$code.'/fb2';
});

if ($router -> isFound ()){$router -> executeHandler ( $router -> getRequestHandler (), $router -> getParams () ); }else { http_response_code ( 404 );echo  '<style>body{display:flex;justify-content:center;}</style><a href = "/"><img src = "https://cdn.dribbble.com/users/1129101/screenshots/3513987/404.gif"></a>' ;} 
?>