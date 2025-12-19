<?php

class Validators
{
    public static function normalizePhone(string $phone): string
    {
        return preg_replace("/\D+/", "", $phone);
    }

    public static function requireString($val, string $field): string
    {
        $s = trim((string)$val);
        if ($s === "") {
            Response::json(["error" => "$field is required"], 422);
        }
        return $s;
    }

    public static function requireChildrenArray($children): array
    {
        if (!is_array($children)) {
            Response::json(["error" => "children must be an array"], 422);
        }
        if (count($children) < 1) {
            Response::json(["error" => "At least one child is required"], 422);
        }
        if (count($children) > 6) {
            Response::json(["error" => "Maximum 6 children allowed"], 422);
        }
        return $children;
    }

    public static function validateChildren(array $children): array
    {
        $clean = [];

        foreach ($children as $i => $c) {
            $name = trim((string)($c["name"] ?? ""));
            if ($name === "") {
                Response::json(["error" => "Child name is required", "index" => $i], 422);
            }

            $ageRaw = $c["age"] ?? null;
            $age = null;

            if ($ageRaw !== null && $ageRaw !== "") {
                if (!is_numeric($ageRaw)) {
                    Response::json(["error" => "Child age must be a number", "index" => $i], 422);
                }
                $age = (int)$ageRaw;
                if ($age < 0 || $age > 18) {
                    Response::json(["error" => "Child age seems invalid", "index" => $i], 422);
                }
            }

            $clean[] = ["name" => $name, "age" => $age];
        }

        return $clean;
    }
}