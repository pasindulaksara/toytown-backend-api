<?php

class ParentsRepository
{
    public static function create(PDO $pdo, string $name, string $phone, array $children): array
    {
        $pdo->beginTransaction();

        try {
            // Insert parent
            $stmt = $pdo->prepare("INSERT INTO parents (name, phone) VALUES (?, ?)");
            $stmt->execute([$name, $phone]);
            $parentId = (int)$pdo->lastInsertId();

            // Insert children
            $childStmt = $pdo->prepare("INSERT INTO children (parent_id, name, age) VALUES (?, ?, ?)");
            $createdChildren = [];

            foreach ($children as $c) {
                $childStmt->execute([$parentId, $c["name"], $c["age"]]);
                $childId = (int)$pdo->lastInsertId();

                $createdChildren[] = [
                    "id" => $childId,
                    "parent_id" => $parentId,
                    "name" => $c["name"],
                    "age" => $c["age"],
                ];
            }

            $pdo->commit();

            return [
                "id" => $parentId,
                "name" => $name,
                "phone" => $phone,
                "children" => $createdChildren,
            ];
        } catch (PDOException $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function getAll(PDO $pdo, string $q = ""): array
{
    $q = trim($q);

    if ($q !== "") {
        $like = "%" . $q . "%";
        $stmt = $pdo->prepare("
            SELECT p.id, p.name, p.phone, p.created_at,
                   (SELECT COUNT(*) FROM children c WHERE c.parent_id = p.id) AS children_count
            FROM parents p
            WHERE p.name LIKE ? OR p.phone LIKE ?
            ORDER BY p.id DESC
            LIMIT 200
        ");
        $stmt->execute([$like, $like]);
        return $stmt->fetchAll();
    }

    $stmt = $pdo->query("
        SELECT p.id, p.name, p.phone, p.created_at,
               (SELECT COUNT(*) FROM children c WHERE c.parent_id = p.id) AS children_count
        FROM parents p
        ORDER BY p.id DESC
        LIMIT 200
    ");
    return $stmt->fetchAll();
}

}