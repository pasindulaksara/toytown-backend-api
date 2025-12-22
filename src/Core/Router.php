<?php

class Router
{
    private array $routes = [];

    public function add(string $method, string $path, $handler): void
    {
        $method = strtoupper(trim($method));
        $path = $this->normalize($path);

        $this->routes[] = [
            "method"  => $method,
            "path"    => $path,
            "handler" => $handler,
            "regex"   => $this->toRegex($path),
        ];
    }

    private function normalize(string $path): string
    {
        $path = trim($path);
        if ($path === "" || $path === "/") return "";
        return trim($path, "/");
    }

    private function toRegex(string $path): string
    {
        if ($path === "") return "#^$#";

        $pattern = preg_replace_callback(
            "#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#",
            fn($m) => "(?P<" . $m[1] . ">[^/]+)",
            $path
        );

        return "#^" . $pattern . "$#";
    }

    public function dispatch(string $method, string $path): void
    {
        $method = strtoupper(trim($method));
        $path = $this->normalize($path);

        foreach ($this->routes as $route) {
            if ($route["method"] !== $method) continue;

            if (preg_match($route["regex"], $path, $matches)) {
                $params = [];
                foreach ($matches as $k => $v) {
                    if (!is_int($k)) $params[$k] = $v;
                }

                $handler = $route["handler"];

                // ["ClassName", "method"] style
                if (is_array($handler) && count($handler) === 2) {
                    [$class, $fn] = $handler;
                    $class::$fn(); // existing controllers use no params
                    return;
                }

                // Closure / callable expects params
                if (is_callable($handler)) {
                    call_user_func($handler, $params);
                    return;
                }

                Response::json(["error" => "Invalid route handler"], 500);
                return;
            }
        }

        Response::json([
            "error" => "Route not found",
            "method" => $method,
            "path" => $path
        ], 404);
    }
}