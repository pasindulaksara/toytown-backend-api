<?php

class Router
{
    private array $routes = [];

    public function add(string $method, string $path, $handler): void
    {
        $this->routes[] = [
            "method" => strtoupper($method),
            "path" => trim($path, "/"),
            "handler" => $handler,
        ];
    }

    public function dispatch(string $method, string $path): void
    {
        $path = trim($path, "/");

        foreach ($this->routes as $route) {
            if ($route["method"] === strtoupper($method) && $route["path"] === $path) {
                $handler = $route["handler"];

                // ["ClassName", "method"]
                if (is_array($handler) && count($handler) === 2) {
                    [$class, $fn] = $handler;
                    $class::$fn();
                    return;
                }

                // Closure / function
                if (is_callable($handler)) {
                    call_user_func($handler);
                    return;
                }

                Response::json(["error" => "Invalid route handler"], 500);
            }
        }

        Response::json([
            "error" => "Route not found",
            "method" => $method,
            "path" => $path
        ], 404);
    }
}