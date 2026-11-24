<?php
declare(strict_types=1);
namespace App\Core;

final class Router
{
    private array $routes = [];

    public function get(string $pattern, callable $h): void { $this->add('GET', $pattern, $h); }
    public function post(string $pattern, callable $h): void { $this->add('POST', $pattern, $h); }

    private function add(string $method, string $pattern, callable $h): void
    {
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern) . '/?$#u';
        $this->routes[] = [$method, $regex, $h];
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = rawurldecode(parse_url($uri, PHP_URL_PATH) ?: '/');
        $path = $path === '' ? '/' : $path;

        $method = $method === 'HEAD' ? 'GET' : $method;

        if ($method === 'POST' && !Csrf::verify()) {
            http_response_code(419);
            View::render('pages/error', ['code' => 419, 'title' => t('error.csrf_title'), 'message' => t('error.csrf_msg')]);
            return;
        }

        foreach ($this->routes as [$m, $regex, $h]) {
            if ($m === $method && preg_match($regex, $path, $mt)) {
                $params = array_filter($mt, 'is_string', ARRAY_FILTER_USE_KEY);
                $h(...array_values($params));
                return;
            }
        }
        http_response_code(404);
        View::render('pages/error', ['code' => 404, 'title' => t('error.404_title'), 'message' => t('error.404_msg')]);
    }
}
