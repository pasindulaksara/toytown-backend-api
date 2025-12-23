<?php

class SessionsRepository
{
    public static function hasActiveSessionForAnyChild(PDO $pdo, array $childIds): bool
    {
        if (count($childIds) === 0) return false;

        $in = implode(",", array_fill(0, count($childIds), "?"));
        $sql = "
            SELECT 1
            FROM sessions s
            JOIN session_children sc ON sc.session_id = s.id
            WHERE s.status = 'active'
              AND sc.child_id IN ($in)
            LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_map("intval", $childIds));
        return (bool)$stmt->fetchColumn();
    }

    public static function createSession(PDO $pdo, int $parentId, string $startTime, string $plannedEndTime, array $childIds): array
    {
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("
                INSERT INTO sessions (parent_id, start_time, planned_end_time, status)
                VALUES (?, ?, ?, 'active')
            ");
            $stmt->execute([$parentId, $startTime, $plannedEndTime]);
            $sessionId = (int)$pdo->lastInsertId();

            $sc = $pdo->prepare("INSERT INTO session_children (session_id, child_id) VALUES (?, ?)");
            foreach ($childIds as $cid) {
                $sc->execute([$sessionId, (int)$cid]);
            }

            $pdo->commit();

            return [
                "id" => $sessionId,
                "parent_id" => $parentId,
                "start_time" => $startTime,
                "planned_end_time" => $plannedEndTime,
                "status" => "active",
                "child_ids" => array_map("intval", $childIds),
            ];
        } catch (PDOException $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function getSessions(PDO $pdo, string $status = ""): array
{
    $status = trim($status);

    if ($status !== "") {
        $stmt = $pdo->prepare("
            SELECT s.*,
                   p.name AS parent_name,
                   p.phone AS parent_phone,
                   (SELECT COUNT(*) FROM session_children sc WHERE sc.session_id = s.id) AS children_count
            FROM sessions s
            JOIN parents p ON p.id = s.parent_id
            WHERE s.status = ?
            ORDER BY s.id DESC
            LIMIT 200
        ");
        $stmt->execute([$status]);
        return $stmt->fetchAll();
    }

    $stmt = $pdo->query("
        SELECT s.*,
               p.name AS parent_name,
               p.phone AS parent_phone,
               (SELECT COUNT(*) FROM session_children sc WHERE sc.session_id = s.id) AS children_count
        FROM sessions s
        JOIN parents p ON p.id = s.parent_id
        ORDER BY s.id DESC
        LIMIT 200
    ");
    return $stmt->fetchAll();
}

public static function getById(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare("
        SELECT s.*,
               p.name AS parent_name,
               p.phone AS parent_phone,
               (SELECT COUNT(*) FROM session_children sc WHERE sc.session_id = s.id) AS children_count
        FROM sessions s
        JOIN parents p ON p.id = s.parent_id
        WHERE s.id = ?
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}


   

    public static function endSession(PDO $pdo, int $id, string $endTime, int $durationMinutes, string $paymentMethod, int $normalPrice, int $discountAmount, int $finalPrice): ?array
    {
        $stmt = $pdo->prepare("
            UPDATE sessions
            SET end_time = ?,
                status = 'completed',
                payment_method = ?,
                duration_minutes = ?,
                normal_price = ?,
                discount_amount = ?,
                final_price = ?
            WHERE id = ? AND status = 'active'
        ");
        $stmt->execute([$endTime, $paymentMethod, $durationMinutes, $normalPrice, $discountAmount, $finalPrice, $id]);

        if ($stmt->rowCount() === 0) return null;

        return self::getById($pdo, $id);
    }
}