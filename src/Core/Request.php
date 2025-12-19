<?php

class Request
{
    public static function method(): string
    {
        return $_SERVER["REQUEST_METHOD"];
    }

    public static function path(): string
    {
        return $_GET["path"] ?? "";
    }

    public static function json(): array
    {
        $raw = file_get_contents("php://input");
        if (!$raw) return [];

        $data = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Response::json(["error" => "Invalid JSON"], 400);
        }
        return $data;
    }
}