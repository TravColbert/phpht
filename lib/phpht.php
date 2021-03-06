<?php
Class PHPHT {
  protected $db = false;
  public $auth = false;
  public $router;
  protected $config;
  protected $manifest;
  private $appurl;

  function __construct($config) {
    $this->config = $config;
    $this->setSessionCookieSettings();
    require_once("lib/phpht_router.php");
    $this->router = new Router(null,$this);
    $this->applang = $config["lang"] ?? "en";
    $this->appname = (isset($config["appname"])) ? $config["appname"] : "PHPHT - Unconfigured";
    $this->views = (isset($config["views"])) ? $config["views"] : "views";
    $this->assets = (isset($config["assets"])) ? $config["assets"] : "public";
    $this->home = (isset($config["home"])) ? $config["home"] : "home.php";
    $this->admin = (isset($config["admin"])) ? $config["admin"] : "admin.php";
    $this->config["appurl"] = (isset($config["appurl"])) ? $config["appurl"] : "localhost.localhost";
    $this->appurl = $this->config["appurl"];
    $this->apiversion = (isset($config["apiversion"])) ? $config["apiversion"] : "api/v1";
    $this->config["baseurl"] = (isset($config["baseurl"])) ? $config["baseurl"] : $this->router->getUrlBase();
    if(!isset($this->config["db"]["auth"])) {
      syslog(LOG_INFO,"No pointer to base authentication db provided. Exiting");
      exit();
    }
    if(!file_exists($this->config["db"]["auth"])) {
      syslog(LOG_INFO,"Base authentication config does not exist. Exiting");
      exit();
    }
    $auth_dbconfig = (file_exists($this->config["db"]["auth"])) ? parse_ini_file($this->config["db"]["auth"]) : null;

    $this->auth_dbtype = (isset($auth_dbconfig["dbtype"])) ? $auth_dbconfig["dbtype"] : "sqlite";
    if($this->auth_dbtype!="sqlite") {
      $this->auth_dbhost = (isset($auth_dbconfig["dbhost"])) ? $auth_dbconfig["dbhost"] : "localhost.localhost";
      $this->auth_dbuser = (isset($auth_dbconfig["dbuser"])) ? $auth_dbconfig["dbuser"] : "phphtdbuser";
      $this->auth_dbpass = (isset($auth_dbconfig["dbpass"])) ? $auth_dbconfig["dbpass"] : "phphtdbpass";
    }
    $this->auth_dblocation = (isset($auth_dbconfig["dblocation"])) ? $auth_dbconfig["dblocation"] : "db/phpht.db";
    if($this->auth_dbtype=="sqlite") {
      $this->auth_db = new \PDO($this->auth_dbtype.":".$this->auth_dblocation);
    } else {
      $this->auth_db = new \PDO($this->auth_dbtype.":host=".$this->auth_dbhost.";dbname=".$this->auth_dblocation,$this->auth_dbuser,$this->auth_dbpass);
    }
    if($this->auth_db) {
      $this->auth = new \Delight\Auth\Auth($this->auth_db);
    }

    // $this->db = new \PDO($this->dbtype.":".$this->dblocation);
    // if($this->db) {
    //   $this->auth = new \Delight\Auth\Auth($this->db);
    // }
    $this->manifest = array(
      "short_name" => "PHPHT PWA",
      "name" => "PHPHT Progressive Web Application Demo",
      "description" => "How to build a PWA with PHPHT",
      "icons" => array(),
      "start_url" => "/demo/",
      "background_color" => "#fafafa",
      "display" => "standalone",
      "scope" => "/",
      "theme_color" => "#fafafa"
    );
    $this->manifest["icons"][] = array(
      "src" => "/public/img/favicons/android-icon-192x192.png",
      "type" => "image/png",
      "sizes" => "192x192"
    );
    $this->manifest["icons"][] = array(
      "src" => "/public/img/favicons/android-icon-512x512.png",
      "type" => "image/png",
      "sizes" => "512x512"
    );
  }

  public function addRoleForUserById($userId, $role) {
    $data = ["messages" => [], "errors" => []];
    try {
      syslog(LOG_INFO,"Attempt apply: Role: $role applied to user: $userId");
      $this->auth->admin()->addRoleForUserById($userId, $role);
      $data["messages"][] = "Role: $role applied to user: $userId";
    }
    catch (\Delight\Auth\UnknownIdException $e) {
      $data["errors"][] = "Failed to add role";
    }
    return $data;
  }

  public function addUserToDomainById($userId, $domainId) {
    
  }

  public function asJSON($data) {
    $this->setBaseHeaders();
    header('Content-Type: application/json;charset=utf-8');
    if(!isset($resultArray["errors"])) $resultArray["errors"] = array();
    if(isset($data["response_code"]) && $data["response_code"]) {
      http_response_code($data["response_code"]);
    }
    $jsonString = json_encode($data);
    echo $jsonString;
  }

  public function asHTML($data) {
    $this->setBaseHeaders();
    header('Content-Type: text/html; charset=UTF-8');
    if(!isset($resultArray["errors"])) $resultArray["errors"] = array();
    if(isset($data["response_code"]) && $data["response_code"]) {
      http_response_code($data["response_code"]);
    }
    syslog(LOG_INFO,$this->toHTML($data));
  }

  public function checkAuth() {
    syslog(LOG_INFO,"checking if user is authenticated...");
    if($this->auth->isLoggedIn()) {
      syslog(LOG_INFO,"user is authenticated");
      return $this->auth->getUserId();
    }
    return $this->goLogin();
  }

  public function checkAuthRole($targetRole) {
    syslog(LOG_INFO,"checking if user has the {$targetRole} role");
    return $this->auth->hasRole($targetRole);
  }

  public function checkAuthDomain($targetDomainId) {
    global $users;
    syslog(LOG_INFO,"checking if user is in the domain ID: {$targetDomainId}");
    return $users->userInDomainId($this->auth->getUserId(),$targetDomainId);
  }

  public function createAppId($length=6) {
    return \Delight\Auth\Auth::createRandomString($length);
  }

  public function dbLastInsertId() {
    return $this->db->lastInsertId();
  }

  public function dbPrepare($sql) {
    syslog(LOG_INFO,"Preparing sql statement for: {$sql}");
    return $this->db->prepare($sql);
  }

  public function dbExecuteQuery($sql) {
    syslog(LOG_INFO,"Executing sql statement: {$sql}");
    return $this->db->exec($sql);
  }

  public function dbGetSqlError() {
    syslog(LOG_INFO,"Getting last SQL error");
    return $this->db->errorInfo();
  }

  public function deleteDelete($matches) {
    global ${$matches[1]};
    $data = ${$matches[1]}->deleteDelete($matches);
    return $this->asJSON($data);
  }

  public function extractPut() {
    // $_PUT = file_get_contents("php://input");
    return json_decode(file_get_contents("php://input"), true);
  }

  public function getConfig($var) {
    return $this->config[$var];
  }

  public function getCurrentDomain($userId=null) {
    global $users;
    syslog(LOG_INFO,"getting user's domain ID");
    if($userId===null) $userId = $this->auth->getUserId();
    return $users->getUserDomain($userId);
  }

  public function getDateTime($dateTimeString=null) {
    return new DateTime($dateTimeString);
  }

  public function getDiag($matches) {
    echo "<pre>";
    if($this->db) {
      echo "DB connection client version: ".$this->db->getAttribute(PDO::ATTR_CLIENT_VERSION)."\n";
    }
    echo "URL Handler-checker\n";
    echo "Matches:\n";
    if($matches[2]) {
      $path = explode("/",$matches[2]);
      array_shift($path);
      array_splice($matches, 2, 1, $path);
    }
    for($c=0;$c<count($matches);$c++) {
      echo "Match ".$c." => ".$matches[$c]."\n";
    }
    echo "Query String Processing:\n";
    var_dump($_GET);
    var_dump($_SERVER['QUERY_STRING']);
    echo "</pre>";
  }

  public function getFavicon($matches) {
    syslog(LOG_INFO,"Skipping favicon");
    return true;
  }

  public function getForm($matches) {
    global ${$matches[1]};
    return ${$matches[1]}->getForm($matches);
  }

  public function getItemsJson($matches) {
    return $this->asJSON($this->getRead($matches));
  }

  public function getManifest($matches) {
    syslog(LOG_INFO,"Getting app manifest");
    return $this->asJSON($this->manifest);
  }

  public function getModelHome($matches) {
    global ${$matches[1]};
    if(!is_a(${$matches[1]},"PHPHT_Model")) {
      syslog(LOG_INFO,"getModelHome(): ".$matches[1]." is not a PHPHT_Model object");
      return $this->view404();
    }
    $modelView = ${$matches[1]}->getView();
    syslog(LOG_INFO,"getModelHome(): {$matches[1]}: Setting view to ".$modelView);
    $reportName = ($matches[1]) ? $matches[1] : null;
    syslog(LOG_INFO,"getModelHome(): {$matches[1]}: query_string ".$_SERVER['QUERY_STRING']);
    return $this->view($modelView,array(
      "pageTitle" => $this->appname,
      "reportName" => $reportName,
      "reportQueryString" => $_SERVER['QUERY_STRING']
    ));
  }

  public function getRead($matches) {
    global ${$matches[1]};
    if(!isset(${$matches[1]})) return $this->view404();
    $resultArray = ${$matches[1]}->getRead($matches);
    if(isset($resultArray['result'])) syslog(LOG_INFO,"Result count: ".count($resultArray['result']));
    $renderer = $this->setRenderer($matches);
    return $this->{$renderer}($resultArray);
  }

  public function getStaticFile($path) {
    syslog(LOG_INFO,"Serving static object: {$path}");
    $fileParts = explode(".",$path);
    $fileExtension = array_pop($fileParts);
    $serveLocation = '';
    switch($fileExtension) {
      case "js":
        syslog(LOG_INFO,"Serving static object of type: Content-Type: text/javascript");
        header('Content-Type: text/javascript');
        $serveLocation = '/public/js';
        break;
      case "json":
        header('Content-Type: text/json');
        break;
      case "css":
        header('Content-Type: text/css');
        $serveLocation = '/public/css';
        break;
      case "pdf":
        header('Content-Type: application/pdf');
        break;
      case "html":
        header('Content-Type: text/html');
        break;
      case "ico":
        header('Content-Type: image/x-icon');
        break;
      case "doc":
        header('Content-Type: application/msword');
        break;
      case "docx":
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        break;
      default:
        header('Content-Type: text/plain');
    };
    syslog(LOG_INFO,"Serving static object from path: ".$path);
    header('Content-Length: '.filesize($path));
    readFile($path);
  }

  public function getUIElement($matches) {
    global ${$matches[1]};
    $resultArray = ${$matches[1]}->getUIElement($matches);
    return $this->asJSON($resultArray);
  } 

  public function getUserId() {
    return $this->auth->getUserId();
  }
  
  public function getVal($var) {
    syslog(LOG_INFO,"Returning: ".$this->$var);
    return $this->$var;
  }

  public function goInit($matches) {
    syslog(LOG_INFO,"starting init");
    if(!isset($_GET["inituser"]) || !isset($_GET["initpass"])) {
      syslog(LOG_INFO,"incorrect parameters passed to _init - bailing");
      return $this->view();
    }
    if($this->superuserExists()) {
      syslog(LOG_INFO,"initial user exists - bailing");
      return $this->view();
    }
    try {
      $userId = $this->auth->register($_GET["inituser"]."@".$this->appurl,$_GET["initpass"],"superuser",null);
      syslog(LOG_INFO,"initial user created");
      $this->auth->admin()->addRoleForUserById($userId,\Delight\Auth\Role::SUPER_ADMIN);
      syslog(LOG_INFO,"roles applied to initial user " . $userId);
      return $this->view("login.php");
    } catch (\Delight\Auth\UserAlreadyExistsException $e) {
      syslog(LOG_INFO,"initial user exists");
      return $this->view("home.php");
    } catch(\Delight\Auth\DatabaseError $e) {
      syslog(LOG_INFO,$e);
      return $this->view404();
    } catch (Error $e) {
      syslog(LOG_DEBUG,$e);
      return $this->view404();
    }
  }

  public function goLogin() {
    syslog(LOG_INFO,"starting auth-check");
    if(!$this->auth->isLoggedIn()) {
      syslog(LOG_INFO,"user not logged-in");
      $_SESSION["last_uri"] = $_SERVER["REQUEST_URI"];
      return $this->view("login.php",array("messages" => array("Please log in")));
    }
    syslog(LOG_INFO,"checking if last URL matches login page: ".preg_match('/\\/login\\/?$/',$_SESSION["last_uri"]));
    if(isset($_SESSION["last_uri"]) && preg_match('/\\/login\\/?$/',$_SESSION["last_uri"])===0) return header('Location: '.$_SESSION["last_uri"]);
    return $this->redirectTo($this->getConfig("baseurl"));
  }

  public function goLogout() {
    syslog(LOG_INFO,"attempting logout...");
    try {
      $this->auth->logOutEverywhere();
      syslog(LOG_INFO,"logout complete");
      return $this->redirectTo($this->getConfig("baseurl"));
    }
    catch (\Delight\Auth\NotLoggedInException $e) {
      syslog(LOG_INFO,"There was an exception logging out but we're doing it anyway");
      return $this->redirectTo($this->getConfig("baseurl"));
    }
  }

  public function goSettings() {

  }

  public function goAdmin() {
    $userId = $this->checkAuth();
    if(!isset($userId)) {
      syslog(LOG_INFO,"user not logged-in");
      $_SESSION["last_uri"] = $_SERVER["REQUEST_URI"];
      return $this->view("login.php",array("messages" => array("Please log in")));
    }
    syslog(LOG_INFO,"starting admin page");
    return $this->view($this->admin);
  }
  
  public function goUsers($matches) {
    $userId = $this->checkAuth();
    if(!isset($userId)) return;
    $domainId = $this->getCurrentDomain($userId);
    return $this->view("users.php");
  }

  public function goVerify($matches) {
    syslog(LOG_INFO,"attempting to verify registration");
    if(!isset($_GET['selector']) || !isset($_GET['token'])) {
      syslog(LOG_INFO,"Someone tried to verify their email with a missing selector or token");
      return $this->view("verified.php",["response_code" => 400]);
    }
    try {
      $this->auth->confirmEmail($_GET['selector'], $_GET['token']);
      echo 'Email address has been verified';
      return $this->redirectTo($this->getConfig("baseurl",["response_code" => 200]));
    }
    catch (\Delight\Auth\InvalidSelectorTokenPairException $e) {
      syslog(LOG_INFO,'Invalid token');
      $this->view();
    }
    catch (\Delight\Auth\TokenExpiredException $e) {
      syslog(LOG_INFO,'Token expired');
      $this->view();
    }
    catch (\Delight\Auth\UserAlreadyExistsException $e) {
      syslog(LOG_INFO,'Email address already exists');
      $this->view();
    }
    catch (\Delight\Auth\TooManyRequestsException $e) {
      syslog(LOG_INFO,'Too many requests');
      $this->view();
    }
  }

  public function hasRole($role) {
    return $this->auth->hasRole($role);
  }

  public function hasAnyRole(...$roles) {
    return $this->auth->hasAnyRole($roles);
  }

  public function hasAllRoles(...$roles) {
    return $this->auth->hasAllRoles($roles);
  }

  public function isAuthorized($roles) {
    foreach($roles as $role) {
      if($this->hasRole($role)) return TRUE;
    }
    return FALSE;
  }

  public function isLoggedIn() {
    if($this->auth->isLoggedIn()) {
      return $this->auth->getUserId();
    }
    return FALSE;
  }

  public function postCreate($matches) {
    global ${$matches[1]};
    $data = ${$matches[1]}->postCreate($_POST);
    return $this->asJSON($data);
  }

  public function postLogin($matches) {
    syslog(LOG_INFO,"attempting login");
    try {
      $this->auth->login($_POST["username"],$_POST["pass"]);
      if(isset($_SESSION["last_uri"])) {
        syslog(LOG_INFO,"Login success");
        syslog(LOG_INFO,"Redirecting to last URI: {$_SESSION["last_uri"]}");
        header('Location: '.$_SESSION["last_uri"]);
      }
      return $this->view();
    }
    catch (\Delight\Auth\InvalidEmailException $e) {
      syslog(LOG_INFO,"invalid email address");
      return $this->view("login.php",array('errors' => array('Wrong email address')));
    }
    catch (\Delight\Auth\InvalidPasswordException $e) {
      syslog(LOG_INFO,"invalid password");
      return $this->view("login.php",array('errors' => array('Wrong password')));
    }
    catch (\Delight\Auth\EmailNotVerifiedException $e) {
      syslog(LOG_INFO,"email has not been verified yet");
      return $this->view("login.php",array('errors' => array('Email not verified')));
    }
    catch (\Delight\Auth\TooManyRequestsException $e) {
      syslog(LOG_INFO,"too many request");
      return $this->view("login.php",array('errors' => array('Too many requests')));
    }
  }

  public function postRegister($matches) {
    syslog(LOG_INFO, "attempting to register user");
    try {
      $userId = $this->auth->register($_POST["email"],$_POST["pass"],null, function ($selector, $token) {
        $mj = new \Mailjet\Client($this->config["mailApiKey"],$this->config["mailSecretKey"],true,['version' => 'v3.1']);
        syslog(LOG_INFO, "user registered - user must be verified");
        syslog(LOG_INFO, "sending email verification");
        $verificationURL = 'https://' . $this->appurl .'/verify/?selector=' . \urlencode($selector) . '&token=' . \urlencode($token);
        syslog(LOG_INFO, "verification URL: $verificationURL");
        $body = [
          'Messages' => [
            [
              'From' => [
                'Email' => "support@" . $this->appurl,
                'Name' => $this->appname . " Support"
              ],
              'To' => [
                [
                  'Email' => $_POST["email"],
                  'Name' => "Support"
                ]
              ],
              'Subject' => "Greetings from " . $this->appname . ".",
              'TextPart' => "Hi ".$_POST['email'].", welcome to " . $this->appname . "! Please go to ".$verificationURL." to verify your account. Thanks!",
              'HTMLPart' => "<h3>Hi ".$_POST['email'].", welcome to <a href='https://" . $this->appname . "/'>" . $this->appname . "</a>!</h3><br />Click <a href='".$verificationURL."'>this link</a> to verify your account.",
              'CustomID' => "AppGettingStartedTest"
            ]
          ]
  	    ];
      
        $response = $mj->post(\Mailjet\Resources::$Email, ['body' => $body]);
        //error_log(print_r($response->getData(),TRUE));
        if($response->success()) {
          syslog(LOG_INFO,"verification email send success");
          $data["response_code"] = 201;
          $data["messages"][] = "User ".$_POST['email']." registered (but not verified)";
        } else {
          syslog(LOG_INFO,"failed to send verification email");
          error_log("failed to send verification email");
          $data["response_code"] = 409;
          $data["errors"][] = "failed to register user";  
        }
        $this->view("registered.php",$data);
      });

      // Set appId for leter reference
      syslog(LOG_INFO,"New userId registered: $userId");
      global $userAppIds;
      $result = $userAppIds->setUserAppId($userId);
      syslog(LOG_INFO,print_r($result,TRUE));

      // Set user_domain mapping
      $dateTime = $this->getDateTime()->format('Y-m-d H:i:s');
      syslog(LOG_INFO,"DateTime is: ".$dateTime);
      $sql = "INSERT INTO users_domains VALUES (".$userId.",0,'".$dateTime."','')";
      $sqlstmt = $this->dbPrepare($sql);
      if($sqlstmt) {
        syslog(LOG_INFO,"adding user to default domain");
        $count = $sqlstmt->execute();
      } else {
        syslog(LOG_INFO,"couldn't add default domain - user will be orphaned");
      }
    }
    catch (\Delight\Auth\InvalidEmailException $e) {
      die('Invalid email address');
    }
    catch (\Delight\Auth\InvalidPasswordException $e) {
        die('Invalid password');
    }
    catch (\Delight\Auth\UserAlreadyExistsException $e) {
        die('User already exists');
    }
    catch (\Delight\Auth\TooManyRequestsException $e) {
        die('Too many requests');
    }
  }

  public function putEdit($matches) {
    global ${$matches[1]};
    $putData = $this->extractPut();
    $data = ${$matches[1]}->putEdit($matches,$putData);
    return $this->asJSON($data);
  }

  public function redirectTo($url = '/') {
    $url = (strlen($url)>0) ? $url : '/'; 
    syslog(LOG_INFO,"Redirecting to: $url");
    header("Location: $url");
  }

  public function setBaseheaders() {
    syslog(LOG_INFO,"Setting base headers");
    header('Strict-Transport-Security: max-age=63072000');
    header('X-Content-Type-Options: nosniff');
    header("Content-Security-Policy: frame-ancestors 'none'");
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    return;
  }

  public function setContentType($matches) {
    $fileParts = explode(".",$matches[2]);
    $fileExtension = array_pop($fileParts);
    switch($fileExtension) {
      case "js":
        header('Content-Type: text/javascript');
        break;
      case "json":
        header('Content-Type: text/json');
        break;
      case "css":
        header('Content-Type: text/css');
        break;
      case "pdf":
        header('Content-Type: application/pdf');
        break;
      case "html":
        header('Content-Type: text/html');
        break;
      case "ico":
        header('Content-Type: image/x-icon');
        break;
      case "doc":
        header('Content-Type: application/msword');
        break;
      case "docx":
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        break;
      default:
        header('Content-Type: text/plain');
    }
    header('Content-Length: '.filesize($matches[1].'/'.$matches[2]));
    return;
  }

  private function setLang() {
    return $this->applang;
  }

  public function setMessages($type,$data) {
    echo "<div id=\"${type}box\" class='messagebox $type column col-12'>";
    if(isset($data[$type])) {
      foreach($data[$type] as $msg) {
        echo "<div id='messageToast' class='toast'>";
        echo "<button class='btn btn-clear float-right' onClick=\"hideElement('#messageToast')\"></button>";
        echo $msg;
        echo "</div>";
      }
    }  
    echo "</div>";
  }

  protected function setRenderer($matches) {
    switch($matches[count($matches)-1]) {
      case ".html":
        syslog(LOG_INFO,"renderer: asHTML");
        return "asHTML";
        break;
      default:
        syslog(LOG_INFO,"renderer: asJSON");
        return "asJSON";
    }
    syslog(LOG_INFO,"error selecting renderer - defaulting to asJSON");
    return "asJSON";
  }

  private function setSessionCookieSettings() {
    syslog(LOG_INFO,"Serving secure cookies");
    \ini_set('session.cookie_domain', $this->appurl);
    \ini_set('session.cookie_path','/');
    \ini_set('session.cookie_httponly', 1);
    \ini_set('session.cookie_secure', 1);
    return;
  }

  private function superuserExists() {
    global $users;
    return $users->superuserExists();
  }

  public function toHTML($data) {
    $htmlString = '';
    for ($i=0; $i < count($data["result"]); $i++) {
      $htmlString .= "<div hx-target='this' hx-swap='outerHTML'>";
      foreach ($data["result"][$i] as $key => $value) {
        syslog(LOG_INFO,$key." ".$value);
        $htmlString .= "<div><label>$key</label>:$value</div>";
      }
      $htmlString .= "<div><button hx-get=\"/".$data["$type"]."/".$data["result"][$i]["id"]."/edit/\" class='btn btn-primary'>Edit</button></div>";
      $htmlString .= "</div>";
    }
    if(count($data["result"])>1) {
      $htmlString = "<ul>".$htmlString."</ul>";
    }
    syslog(LOG_INFO,$htmlString);
    return $htmlString;
  }

  public function view($template=null,$data=[]) {
    $template = (isset($template)) ? $template : $this->home;
    if(!file_exists($this->views."/".$template)) {
      return $this->view404($this->views."/".$template);
    }
    $pageTitle = $this->appname;
    if($data) {
      if(array_key_exists("pageTitle",$data)) $pageTitle = $data['pageTitle'];
    }
    $data["appname"] = $this->appname;
    $this->setBaseHeaders();
    syslog(LOG_INFO,"Getting view: $template");
    include($this->views."/head.php");
    include($this->views."/navbar.php");
    include($this->views."/".$template);
    include($this->views."/foot.php");
  }

  public function view404($page = "") {
    syslog(LOG_INFO, "page: ${page} not found");
    $data = array(
      'pageTitle' => "4 oh 4",
      'errors' => array("404 - page not found")
    );
    http_response_code(404);
    return $this->view("404.php",$data);
    exit;
  }

  public function viewHelp() {
    return $this->view("help.php");
  }

  public function viewInfo() {
    // global $router;
    syslog(LOG_INFO,"Showing server info phpinfo()");
    return $this->view("info.php");
  }

  public function viewRegister() {
    syslog(LOG_INFO,"Showing registration (sign-up) page");
    return $this->view("register.php");
  }  
}
