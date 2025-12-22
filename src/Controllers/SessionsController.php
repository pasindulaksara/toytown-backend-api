<?php

class SessionsController
{
    private static function nowSql(): string
    {
        return date("Y-m-d H:i:s");
    }

    private static function closingTimeSqlForDate(string $dateYmd): string
    {
        return $dateYmd . " 20:00:00";
    }

    private static function minSql(string $a, string $b): string
    {
        return (strtotime($a) <= strtotime($b)) ? $a : $b;
    }

    /**
     * Round minutes UP to nearest 30-min block, with minimum 30 minutes if > 0.
     * Examples:
     * 0  -> 0
     * 7  -> 30
     * 30 -> 30
     * 31 -> 60
     * 61 -> 90
     */
    private static function billableMinutes(int $rawMinutes): int
    {
        if ($rawMinutes <= 0) return 0;
        if ($rawMinutes < 30) return 30;
        return (int)(ceil($rawMinutes / 30) * 30);
    }

    public static function start(): void
    {
        $body = Request::json();

        $parentId = (int)($body["parent_id"] ?? 0);
        $childIds = $body["child_ids"] ?? [];
        $plannedMinutes = (int)($body["planned_minutes"] ?? 120);

        if ($parentId <= 0) {
            Response::json(["error" => "parent_id is required"], 422);
        }
        if (!is_array($childIds) || count($childIds) === 0) {
            Response::json(["error" => "child_ids is required"], 422);
        }
        if ($plannedMinutes <= 0) {
            Response::json(["error" => "planned_minutes must be > 0"], 422);
        }

        $childIds = array_values(array_unique(array_map("intval", $childIds)));

        $pdo = db();

        // Prevent double-active (a child can't be in 2 active sessions)
        if (SessionsRepository::hasActiveSessionForAnyChild($pdo, $childIds)) {
            Response::json(["error" => "One or more selected children already have an active session"], 409);
        }

        $start = self::nowSql();
        $date = date("Y-m-d", strtotime($start));

        $idealPlannedEnd = date("Y-m-d H:i:s", strtotime($start . " +{$plannedMinutes} minutes"));
        $closing = self::closingTimeSqlForDate($date);

        // If already after closing, block
        if (strtotime($start) >= strtotime($closing)) {
            Response::json(["error" => "Shop is closed. Cannot start a session after 8:00 PM."], 422);
        }

        // Cap planned end at 8PM
        $plannedEnd = self::minSql($idealPlannedEnd, $closing);

        try {
            $session = SessionsRepository::createSession($pdo, $parentId, $start, $plannedEnd, $childIds);
            Response::json(["ok" => true, "data" => $session], 201);
        } catch (PDOException $e) {
            Response::json(["error" => "Database error", "message" => $e->getMessage()], 500);
        }
    }

    public static function index(): void
    {
        $status = $_GET["status"] ?? "";
        $pdo = db();

        $rows = SessionsRepository::getSessions($pdo, (string)$status);

        Response::json(["ok" => true, "data" => $rows]);
    }

    public static function end(int $id): void
    {
        $body = Request::json();

        // Optional (keep NULL allowed in DB if you want)
        $paymentMethod = trim((string)($body["payment_method"] ?? "cash"));
        if ($paymentMethod === "") $paymentMethod = "cash";

        // Only allow these for now (optional strictness)
        $allowed = ["cash", "bank_transfer"];
        if (!in_array($paymentMethod, $allowed, true)) {
            Response::json(["error" => "Invalid payment_method. Use cash or bank_transfer"], 422);
        }

        $pdo = db();
        $session = SessionsRepository::getById($pdo, $id);

        if (!$session) {
            Response::json(["error" => "Session not found"], 404);
        }
        if (($session["status"] ?? "") !== "active") {
            Response::json(["error" => "Session is not active"], 409);
        }

        $start = (string)$session["start_time"];
        $now = self::nowSql();

        // Cap ending time at 8PM of the start date
        $date = date("Y-m-d", strtotime($start));
        $closing = self::closingTimeSqlForDate($date);
        $endTime = self::minSql($now, $closing);

        $startTs = strtotime($start);
        $endTs = strtotime($endTime);

        // Actual minutes (raw)
        $diffSeconds = max($endTs - $startTs, 0);
        $rawMinutes = (int)ceil($diffSeconds / 60);
        $billableMinutes = self::billableMinutes($rawMinutes);

        // Billable minutes (human-friendly rounding)
        $billableMinutes = self::billableMinutes($rawMinutes);

        $childrenCount = (int)($session["children_count"] ?? 0);
        if ($childrenCount <= 0) $childrenCount = 1;

        // Price: Rs 1000 per hour per child
        $normalPrice = (int)round(($billableMinutes / 60) * 1000 * $childrenCount);

        // Discount logic later (rewards)
        $discountAmount = 0;
        $finalPrice = max($normalPrice - $discountAmount, 0);

        // For now we store BILLABLE minutes in duration_minutes
        // (If later you add a billable_minutes column, we can store both.)
        $durationMinutes = $billableMinutes;

        $updated = SessionsRepository::endSession(
            $pdo,
            $id,
            $endTime,
            $durationMinutes,
            $paymentMethod,
            $normalPrice,
            $discountAmount,
            $finalPrice
        );

        if (!$updated) {
            Response::json(["error" => "Unable to end session"], 500);
        }

        Response::json(["ok" => true, "data" => $updated]);
    }
}