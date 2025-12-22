<?php

class ParentsController
{
    public static function store(): void
    {
        $body = Request::json();

        $name = Validators::requireString($body["name"] ?? "", "Parent name");
        $phone = Validators::normalizePhone((string)($body["phone"] ?? ""));
        if ($phone === "") {
            Response::json(["error" => "WhatsApp number is required"], 422);
        }

        $childrenRaw = Validators::requireChildrenArray($body["children"] ?? []);
        $children = Validators::validateChildren($childrenRaw);

        $pdo = db();

        try {
            $parent = ParentsRepository::create($pdo, $name, $phone, $children);

            Response::json([
                "ok" => true,
                "parent" => $parent
            ], 201);
        } catch (PDOException $e) {
            // Duplicate phone (unique constraint)
            if ((int)$e->getCode() === 23000) {
                Response::json(["error" => "This WhatsApp number is already registered"], 409);
            }
            Response::json(["error" => "Database error", "message" => $e->getMessage()], 500);
        }
    }
    public static function index(): void
{
    $q = $_GET["q"] ?? "";
    $pdo = db();

    $rows = ParentsRepository::getAll($pdo, (string)$q);

    Response::json([
        "ok" => true,
        "data" => $rows
    ]);
}

public static function show(int $id): void
{
    $pdo = db();

    $parent = ParentsRepository::getByIdWithChildren($pdo, $id);

    if (!$parent) {
        Response::json(["ok" => false, "error" => "Parent not found"], 404);
    }

    Response::json([
        "ok" => true,
        "data" => $parent
    ]);
}

}