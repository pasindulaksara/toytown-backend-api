<?php

class EarningsController
{
    private static function rangeWhere(string $range): array
    {
        // uses end_time as the truth for earnings
        // only completed sessions count
        if ($range === "today") {
            return ["DATE(end_time) = CURDATE()"];
        }
        if ($range === "week") {
            return ["YEARWEEK(end_time, 1) = YEARWEEK(CURDATE(), 1)"];
        }
        if ($range === "month") {
            return ["YEAR(end_time) = YEAR(CURDATE()) AND MONTH(end_time) = MONTH(CURDATE())"];
        }
        return ["1=1"]; // all time
    }

    public static function summary(): void
    {
        $range = $_GET["range"] ?? "today";
        $pdo = db();

        $whereParts = self::rangeWhere((string)$range);
        $where = implode(" AND ", $whereParts);

        $sql = "
            SELECT
              COUNT(*) AS completed_sessions,
              COALESCE(SUM(final_price),0) AS total_revenue,
              COALESCE(SUM(discount_amount),0) AS total_discounts,
              COALESCE(SUM(CASE WHEN payment_method='cash' THEN final_price ELSE 0 END),0) AS cash_total,
              COALESCE(SUM(CASE WHEN payment_method='bank_transfer' THEN final_price ELSE 0 END),0) AS bank_total
            FROM sessions
            WHERE status='completed'
              AND end_time IS NOT NULL
              AND {$where}
        ";

        $stmt = $pdo->query($sql);
        $row = $stmt->fetch();

        Response::json(["ok" => true, "data" => $row]);
    }

    public static function transactions(): void
    {
        $range = $_GET["range"] ?? "today";
        $pdo = db();

        $whereParts = self::rangeWhere((string)$range);
        $where = implode(" AND ", $whereParts);

        $sql = "
            SELECT
              s.id,
              s.parent_id,
              p.name AS parent_name,
              p.phone AS parent_phone,
              s.start_time,
              s.end_time,
              s.duration_minutes,
              s.payment_method,
              s.normal_price,
              s.discount_amount,
              s.final_price
            FROM sessions s
            JOIN parents p ON p.id = s.parent_id
            WHERE s.status='completed'
              AND s.end_time IS NOT NULL
              AND {$where}
            ORDER BY s.end_time DESC
            LIMIT 200
        ";

        $stmt = $pdo->query($sql);
        $rows = $stmt->fetchAll();

        Response::json(["ok" => true, "data" => $rows]);
    }
}