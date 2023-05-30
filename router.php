<?php

/**
 * defines if the router should rewrite non termating routes with /
 * with it
 */
define('MANDATORY_END_SLASHES', 1);

/**
 * defines if the router should rewrite non-https to https
 */
define('FORCE_HTTPS', 1);

/**
 * defines prefixes that the router should rewrite to 403
 */
define('PROTECTED_PREFIXES', [
    "/.git",
    "/system",
    "/app.ini",
    "/.env"
]);

// ----------------------------------------------------------------

if (isset($_SERVER["HTTP_X_FORWARDED_HOST"])) {
    define('HOSTNAME', $_SERVER["HTTP_X_FORWARDED_HOST"]);
} else {
    define('HOSTNAME', $_SERVER["HTTP_HOST"]);
}

if (isset($_SERVER["HTTP_X_FORWARDED_PROTO"])) {
    define('IS_SECURE', strpos($_SERVER["HTTP_X_FORWARDED_PROTO"], 'https') >= 0);
} else {
    define('IS_SECURE', isset($_SERVER["HTTPS"]));
}

$routes = [];

function set_route(string $method, string $path, array|string|callable $action)
{
    global $routes;
    $routes[] = [
        "method" => $method,
        "path" => $path,
        "action" => $action
    ];
}

function router_execute()
{
    global $routes;

    foreach (PROTECTED_PREFIXES as $prefix) {
        if (str_starts_with($_SERVER['REQUEST_URI'], $prefix)) {
            http_response_code(403);
            die;
        }
    }

    $url = parse_url($_SERVER['REQUEST_URI']);
    $pathinfo = pathinfo($_SERVER['REQUEST_URI'], PATHINFO_ALL);

    $reqUri = rtrim($url["path"], '/');
    $method = $_SERVER['REQUEST_METHOD'];

    if (FORCE_HTTPS && !IS_SECURE) {
        header("Location: https://" . HOSTNAME . "" . $reqUri);
        die;
    }

    if (MANDATORY_END_SLASHES === 1) {
        if (!empty($pathinfo['extension'])) {
            goto resumeRedirect;
        } else if (!str_ends_with($url["path"], '/')) {
            $newPath = $url["path"] . "/";
            if (isset($url["query"])) {
                $newPath .= "?" . $url["query"];
            }
            http_response_code(301);
            header("Location: " . $newPath);
            die;
        }
    }

    resumeRedirect:
    $reqUriParts = explode('/', $reqUri);

    foreach ($routes as $route) {
        $query = [];

        $routPath = $route["path"];
        $routMeth = $route["method"];

        $routPath = rtrim($routPath, '/');

        $routPathParts = explode('/', $routPath);

        if (sizeof($routPathParts) != sizeof($reqUriParts)) {
            continue;
        }

        for ($i = 0; $i < sizeof($reqUriParts); $i++) {
            $routPtt = $routPathParts[$i];
            $reqsPtt = $reqUriParts[$i];

            $methodMatched = false;
            $pathMatched = false;
            if ($routMeth == "ANY") {
                $methodMatched = true;
            } else if (strtolower($method) == strtolower($routMeth)) {
                $methodMatched = true;
            } else {
                continue 2;
            }

            if (strtolower($routPtt) == strtolower($reqsPtt)) {
                $pathMatched = true;
            } else if (str_starts_with($routPtt, '<') && str_ends_with($routPtt, '>')) {
                $name = substr($routPtt, 1, strlen($routPtt) - 2);
                $query[$name] = $reqsPtt;
                $pathMatched = true;
            } else {
                continue 2;
            }

            if ($i == sizeof($reqUriParts) - 1 && $methodMatched && $pathMatched) {
                call_user_func($route["action"], $query);
                return;
            }
        }
    }

    http_response_code(404);
}
