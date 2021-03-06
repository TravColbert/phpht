<?php
$config = (file_exists("./config/config.ini")) ? parse_ini_file("./config/config.ini") : null;
openlog((isset($config["appname"])) ? $config["appname"] : "phpht", LOG_PID, LOG_SYSLOG);

$vendor = (isset($config["vendor"])) ? $config["vendor"] : "/vendor";
require __DIR__ . $vendor . '/autoload.php';

$libdir = (isset($config["libdir"])) ? $config["libdir"] : "/lib";
require_once(__DIR__ . $libdir . "/phpht.php");
$phpht = new PHPHT($config);

// Setup what folder your static assets are pulled from (e.g. 'public')
$phpht->router->assets();

require_once(__DIR__ . $libdir . "/phpht_model.php");

$users = new User($phpht);
$domains = new Domain($phpht);
$roles = new Role($phpht);
$userAppIds = new UserAppId($phpht);

$includedir = (isset($config["includedir"])) ? $config["includedir"] : "/includes";
if(file_exists(__DIR__ . $includedir . "/includes.php")) include(__DIR__ . $includedir . "/includes.php");

/**
 * == DEFINING ROUTES ==
 * Once, all your model objects are created we can start defining
 * the routes that tie URL requests to PHPHT to methods.
 * 
 * Routes are defined with the $phpht->router object.
 * 
 * The format of a route definition is:
 * 
 * $phpht->router->verb(path_regex, function);
 * 
 * like:
 * 
 * e.g.: $phpht->router->get("/^\\/_info\\/?/","showServerInfo");
 * 
 * where:
 * 
 * - verb : GET, POST, PUSH, DELETE
 * - path_regex : a regex that, when matched, triggers the running.
 *                of the supplied function. The path_regex might also 
 *                build a $matches array that contains all the parts
 *                of the URI. The $matches array gets passed to the 
 *                function. This allows the function to act on the 
 *                specifics of the URI call.
 *                You want to define more specific paths at the top
 *                and more general paths near the bottom.
 * - function : the function that is called when the URI matches
 *              the path_regex.
 *              The function takes a $matches array as an argument.
 * 
 * notes:
 * 
 * The path_regex does not include the root elements of the URI. So,
 * in GET https://my.server/myapp/_info/ (from above), the 
 * 'my.server/myapp' part of the URI does not need to be defined in 
 * the regex. The regex would look like this:
 * 
 * $phpht->router->get("/^\\/_info\\/?/","showServerInfo");
 * 
 * The regex just catches: '/_info/'.
 * 
 * There is another form to the 'function' parameter where an array is
 * given like this:
 * 
 * $phpht->router->get("/^\\/_info\\/?/",array("phpht","showServerInfo"))
 * 
 * In this case, the first element of the array is an object and the 
 * second element is a method in the object. We often use this format 
 * when using PHPHT's internal methods.
 *
 * The following 'standard' routes are version-independant and probably don't
 * need to change if you change the API.
 */
$phpht->router->get("/^\\/manifest\.webmanifest$/",array($phpht,"getManifest"));
$phpht->router->get("/^\\/favicon\.ico$/",array($phpht,"getFavicon"));
$phpht->router->get("/^\\/_info\\/?$/",array($phpht,"viewInfo"));
$phpht->router->get("/^\\/(_init)(\\/.+)*\\/?$/",array($phpht,"goInit"));
$phpht->router->get("/^\\/(_diag)(\\/.+)*\\/?$/",array($phpht,"getDiag"));
$phpht->router->get("/^\\/404\\/?$/",array($phpht,"view404"));
$phpht->router->get("/^\\/login\\/?$/",array($phpht,"goLogin"));
$phpht->router->get("/^\\/logout\\/?$/",array($phpht,"goLogout"));
$phpht->router->get("/^\\/register\\/?$/",array($phpht,"viewRegister"));
$phpht->router->get("/^\\/(verify)(\\/.+)*\\/?$/",array($phpht,"goVerify"));
$phpht->router->get("/^\\/settings\\/?$/",array($phpht,"goSettings"));
$phpht->router->get("/^\\/admin\\/?$/",array($phpht,"goAdmin"));
$phpht->router->get("/^\\/myusers\\/?$/",array($phpht,"goUsers"));
$phpht->router->post("/^\\/register\\/?$/",array($phpht,"postRegister"));
$phpht->router->post("/^\\/login\\/?$/",array($phpht,"postLogin"));

/**
* Your custom routes go here
*/
$routedir = (isset($config["routedir"])) ? $config["routedir"] : "/routes";
if(file_exists(__DIR__ . $routedir ."/routes.php")) include(__DIR__ . $routedir ."/routes.php");

/**
 * These are generic routes that work with basic, non-compound objects
 * As soon as you define them and create the model, they should Just Work
 *
 * These calls are version-dependant and may be overridden in your own 
 * routes.php file. You might, for example, have a 'v2' version of the below 
 * routes in your routes file.
 *
 * These API version routes essentially differentiate between calls for 'pages'
 * and calls for other resources.
 */
$phpht->router->get("/^\\/api\\/v1\\/([^\\/]+)\\/(edit)\\/([0-9]+)\\/?/",array($phpht,"getForm"));
$phpht->router->get("/^\\/api\\/v1\\/([^\\/]+)\\/(create)\\/?/",array($phpht,"getForm"));
$phpht->router->get("/^\\/api\\/v1\\/([^\\/]+)\\/new\\/?$/","setupNewObject");
$phpht->router->get("/^\\/api\\/v1\\/([^\\/]+)\\/([0-9]+)\\/edit\\/?$/","toForm");
$phpht->router->get("/^\\/api\\/v1\\/([^\\/]+)\\/ui\\/([^\\/]+)\\/?$/",array($phpht,"getUIElement"));
$phpht->router->get("/^\\/api\\/v1\\/([^\\/]+)\\/([0-9]+)\\/?$/",array($phpht,"getRead"));
// Regex for catching extensions like '.html'
// $phpht->router->get("/^\\/api\\/v1\\/([^\\/]+)\\/([0-9]+)(\.[^\\/]+)?\\/?$/",array($phpht,"getRead"));
// $phpht->router->get("/^\\/([^\\/]+)\\/(json)\\/?$/",array($phpht,"getItemsJson"));
$phpht->router->get("/^\\/api\\/v1\\/([^\\/]+)\\/?$/",array($phpht,"getRead"));
$phpht->router->post("/^\\/api\\/v1\\/([^\\/]+)\\/?$/",array($phpht,"postCreate"));
$phpht->router->put("/^\\/api\\/v1\\/([^\\/]+)\\/([0-9]+)\\/?$/",array($phpht,"putEdit"));
$phpht->router->delete("/^\\/api\\/v1\\/([^\\/]+)\\/([0-9]+)\\/?$/",array($phpht,"deleteDelete"));

/**
 * THE ROOT ROUTE
 * 
 * This defines the root route: the front door to your app.
 */
$phpht->router->get("/^\\/?$/",function($matches) use ($phpht) {
  $phpht->view($phpht->getVal("home"),$matches);
});

/**
 * Find and run the route!
 */
$phpht->router->route();
