<?php

class ParentsRepository
{
    // Rewards rule
    private const REWARD_BLOCK_MINUTES = 480; // 8 hours

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

    /**
     * Parent profile = parent + children + rewards summary + recent sessions
     * (No schema change required)
     */
    public static function getByIdWithChildren(PDO $pdo, int $id): ?array
    {
        // parent
        $stmt = $pdo->prepare("SELECT id, name, phone, created_at FROM parents WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $parent = $stmt->fetch();

        if (!$parent) return null;

        // children
        $cStmt = $pdo->prepare("SELECT id, parent_id, name, age FROM children WHERE parent_id = ? ORDER BY id ASC");
        $cStmt->execute([$id]);
        $children = $cStmt->fetchAll();
        $parent["children"] = $children;

        // -------- Rewards + totals (COMPLETED sessions only) --------
        $sumStmt = $pdo->prepare("
            SELECT
                COALESCE(SUM(COALESCE(s.duration_minutes, 0)), 0) AS total_minutes,
                COALESCE(COUNT(*), 0) AS total_sessions
            FROM sessions s
            WHERE s.parent_id = ?
              AND s.status = 'completed'
        ");
        $sumStmt->execute([$id]);
        $sumRow = $sumStmt->fetch() ?: ["total_minutes" => 0, "total_sessions" => 0];

        $totalMinutes = (int)($sumRow["total_minutes"] ?? 0);
        $totalSessions = (int)($sumRow["total_sessions"] ?? 0);

        $rewardsEarned = (int)floor($totalMinutes / self::REWARD_BLOCK_MINUTES);

        // NOTE: Without a DB field/table, we can't persist "used".
        // For now keep it 0. Later we add parents.rewards_used or a rewards table.
        $rewardsUsed = 0;
        $rewardsAvailable = max($rewardsEarned - $rewardsUsed, 0);

        $remainder = $totalMinutes % self::REWARD_BLOCK_MINUTES;
        $upcomingRewardInMinutes = ($remainder === 0) ? 0 : (self::REWARD_BLOCK_MINUTES - $remainder);

        $parent["total_minutes_played"] = $totalMinutes;
        $parent["total_sessions"] = $totalSessions;
        $parent["rewards_earned"] = $rewardsEarned;
        $parent["rewards_used"] = $rewardsUsed;
        $parent["rewards_available"] = $rewardsAvailable;
        $parent["upcoming_reward_in_minutes"] = $upcomingRewardInMinutes;

        // -------- Recent sessions (last 10, active + completed) --------
        $sStmt = $pdo->prepare("
            SELECT
                s.id,
                s.parent_id,
                s.start_time,
                s.planned_end_time,
                s.end_time,
                s.status,
                s.payment_method,
                COALESCE(s.duration_minutes, 0) AS duration_minutes,
                COALESCE(s.normal_price, 0) AS normal_price,
                COALESCE(s.discount_amount, 0) AS discount_amount,
                COALESCE(s.final_price, 0) AS final_price,
                (
                    SELECT COUNT(*)
                    FROM session_children sc
                    WHERE sc.session_id = s.id
                ) AS children_count
            FROM sessions s
            WHERE s.parent_id = ?
            ORDER BY s.id DESC
            LIMIT 10
        ");
        $sStmt->execute([$id]);
        $parent["recent_sessions"] = $sStmt->fetchAll();

        return $parent;
    }
}