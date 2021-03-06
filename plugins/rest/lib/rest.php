<?php

/*
 *
 */

class rex_yform_rest
{
    protected $config = [];
    protected $route = '';
    public static $status = [
        200 => '200 OK',
        201 => '201 Created', // for POST Created resource with Link
        // 201 – OK – New resource has been created
        204 => '204 No Content',
        // 204 – OK – The resource was successfully deleted
        304 => '304 – Not Modified',
        400 => '400 Bad Request',
        401 => '401 Unauthorized',
        403 => '403 Forbidden',
        404 => '404 Not Found',
        405 => '405 Method Not Allowed',
        500 => '500 Internal Server Error',
    ];
    public static $preRoute = '/rest';

    protected static $routes = [];

    public static function addRoute($route)
    {
        self::$routes[] = $route;
    }

    public static function getRoutes()
    {
        return self::$routes;
    }

    public static function getCurrentPath()
    {
        $url = parse_url($_SERVER['REQUEST_URI']);
        return $url['path'] ?? '';
    }

    public static function handleRoutes()
    {
        // kreatif: rest route identifaction fix
        if (class_exists('\rex_yrewrite')) {
            $currentPath = str_replace(rex_yrewrite::getCurrentDomain()->getPath(), '/', self::getCurrentPath());
        } else {
            $currentPath = str_replace($_SERVER['BASE'], '', self::getCurrentPath());
        }

        if ('' != self::$preRoute) {
            if (substr($currentPath, 0, strlen(self::$preRoute)) != self::$preRoute) {
                return false;
            }
        }

        foreach (self::$routes as $route) {
            $routePath = self::$preRoute . $route->getPath();

            if (substr($currentPath, 0, strlen($routePath)) != $routePath) {
                continue;
            }

            $paths = explode('/', substr($currentPath, strlen($routePath)));

            $paths = array_filter($paths, static function ($p) {
                if (!empty($p)) {
                    return true;
                }
                return false;
            });

            /** @var \rex_yform_rest_route $route */

            // kreatif: $paths added
            if (!$route->hasAuth($paths)) {
                self::sendError(401, 'no-access');
            } else {
                $paths = \rex_extension::registerPoint(new \rex_extension_point('YFORM_REST_HANDLE_ROUTE_PATHS', $paths));
                $route
                ->handleRequest($paths, $_GET);
            }
        }
    }

    public static function sendError($status = '404', $error = 'error', $descriptions = [])
    {
        $message = [];
        $message['errors'] = [
            'message' => $error,
            'status' => $status,
            'descriptions' => $descriptions,
        ];
        self::sendContent($status, $message);
    }

    public static function sendContent($status, $content, $contentType = 'application/json')
    {
        // kreatif: EP added for wildcard::parse
        $content = \rex_extension::registerPoint(new \rex_extension_point('YFORM_REST_SEND_CONTENT', json_encode($content)));
        \rex_response::setStatus(self::$status[$status]);
        \rex_response::sendContent($content, $contentType);
        exit;
    }

    public static function getHeader($key = '', $default = '')
    {
        $value = '';

        $headers = [];

        foreach ($_SERVER as $k => $v) {
            if ('HTTP_' == substr($k, 0, 5)) {
                $headers[str_replace(' ', '-', strtolower(str_replace('_', ' ', substr($k, 5))))] = $v;
            } elseif ('CONTENT_TYPE' == $k) {
                $headers['Content-Type'] = $v;
            } elseif ('CONTENT_LENGTH' == $k) {
                $headers['Content-Length'] = $v;
            }
        }

        if (array_key_exists(strtolower($key), $headers)) {
            $value = $headers[strtolower($key)];
        }

        if ('' == $value) {
            $value = rex_get($key, 'string', '');
        }

        if ('' == $value) {
            $value = $default;
        }

        return $value;
    }

    public static function getLinkByPath($route, $params = [], $additionalPaths = [])
    {
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && 'https' == $_SERVER['HTTP_X_FORWARDED_PROTO']) {
            $url = 'https://';
        } elseif ((isset($_SERVER['SERVER_PORT']) && 443 == $_SERVER['SERVER_PORT']) || (isset($_SERVER['HTTPS']) && 'off' != strtolower($_SERVER['HTTPS']))) {
            $url = 'https://';
        } else {
            $url = 'http://';
        }

        if (isset($_SERVER['HTTP_X_FORWARDED_SERVER'])) {
            $url .= $_SERVER['HTTP_X_FORWARDED_SERVER'];
        } else if(class_exists('\rex_yrewrite')) {
            // kreatif: this check is needed for subfolder domains
            $domain = rex_yrewrite::getCurrentDomain();
            $url .= trim($domain->getHost() . $domain->getPath(), '/');
        } else {
            // kreatif: url base added
            $url .= @$_SERVER['HTTP_HOST'] . $_SERVER['BASE'];
        }

        $query = http_build_query($params, '', '&');
        $query = ('' != $query) ? '?' . $query : $query;

        $path = implode('/', array_merge([$route->getPath()], $additionalPaths));

        return $url . self::$preRoute . $path . $query;
    }

    public static function getRouteByInstance($instance)
    {
        $instanceType = get_class($instance);

        foreach (self::$routes as $route) {
            if ($route->type == $instanceType) {
                return $route;
            }
        }

        return null;
    }

    public static function getCurrentUrl()
    {
        return $_SERVER['REQUEST_URI'];
    }
}
