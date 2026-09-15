<?php

require_once __DIR__ . '/../../config/database.php';

class Report
{
    public const PERIODS = ['day', 'week', 'month', 'year'];

    private static function db(): PDO
    {
        return getConnection();
    }

    public static function normalizePeriod(?string $period): string
    {
        return in_array($period, self::PERIODS, true) ? $period : 'day';
    }

    public static function periodLabel(string $period): string
    {
        return [
            'day' => 'Hoy',
            'week' => 'Últimos 7 días',
            'month' => 'Este mes',
            'year' => 'Este año',
        ][self::normalizePeriod($period)];
    }

    /**
     * SQL condition for the given period. $period is always normalized
     * through normalizePeriod() before reaching here, and $column is only
     * ever a hardcoded literal passed by this class, never user input.
     */
    private static function periodCondition(string $period, string $column = 'created_at'): string
    {
        return match ($period) {
            'week' => "$column >= (CURDATE() - INTERVAL 6 DAY)",
            'month' => "$column >= DATE_FORMAT(CURDATE(), '%Y-%m-01')",
            'year' => "$column >= DATE_FORMAT(CURDATE(), '%Y-01-01')",
            default => "DATE($column) = CURDATE()",
        };
    }

    public static function totalSold(int $tenantId, string $period = 'day'): float
    {
        $period = self::normalizePeriod($period);
        $condition = self::periodCondition($period);

        $stmt = self::db()->prepare(
            "SELECT COALESCE(SUM(total), 0) AS total
             FROM sales
             WHERE tenant_id = :tenant_id AND status = 'completed' AND $condition"
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        return (float) $stmt->fetch()['total'];
    }

    public static function topProduct(int $tenantId, string $period = 'day'): ?array
    {
        $products = self::topProducts($tenantId, $period, 1);

        return $products[0] ?? null;
    }

    public static function topProducts(int $tenantId, string $period = 'day', int $limit = 5): array
    {
        $period = self::normalizePeriod($period);
        $condition = self::periodCondition($period, 's.created_at');

        $stmt = self::db()->prepare(
            "SELECT p.id, p.name, SUM(si.quantity) AS quantity_sold
             FROM sale_items si
             JOIN sales s ON s.id = si.sale_id
             JOIN products p ON p.id = si.product_id
             WHERE s.tenant_id = :tenant_id AND s.status = 'completed' AND $condition
             GROUP BY p.id, p.name
             ORDER BY quantity_sold DESC
             LIMIT " . max(1, $limit)
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        return $stmt->fetchAll();
    }

    /**
     * Revenue, cost and margin for the given period. The cost side uses
     * each product's current cost (not a historical snapshot), since that
     * is the only cost data the schema tracks.
     */
    public static function margin(int $tenantId, string $period = 'day'): array
    {
        $period = self::normalizePeriod($period);
        $condition = self::periodCondition($period, 's.created_at');

        $stmt = self::db()->prepare(
            "SELECT COALESCE(SUM(si.subtotal), 0) AS revenue, COALESCE(SUM(si.quantity * p.cost), 0) AS cost
             FROM sale_items si
             JOIN sales s ON s.id = si.sale_id
             JOIN products p ON p.id = si.product_id
             WHERE s.tenant_id = :tenant_id AND s.status = 'completed' AND $condition"
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        $row = $stmt->fetch();

        $revenue = (float) $row['revenue'];
        $cost = (float) $row['cost'];
        $margin = $revenue - $cost;

        return [
            'revenue' => $revenue,
            'cost' => $cost,
            'margin' => $margin,
            'margin_pct' => $revenue > 0 ? ($margin / $revenue) * 100 : 0.0,
        ];
    }

    /**
     * Sales totals bucketed to match the period: hourly for "day", daily for
     * "week"/"month", monthly for "year". Each series runs from the start of
     * the period up to now, with empty buckets filled as zero.
     *
     * @return array<int, array{label: string, total: float}>
     */
    public static function salesTrend(int $tenantId, string $period = 'day'): array
    {
        $period = self::normalizePeriod($period);

        return match ($period) {
            'week' => self::dailyTrend($tenantId, 6),
            'month' => self::dailyTrend($tenantId, (int) date('j') - 1, true),
            'year' => self::monthlyTrend($tenantId),
            default => self::hourlyTrend($tenantId),
        };
    }

    private static function hourlyTrend(int $tenantId): array
    {
        $stmt = self::db()->prepare(
            "SELECT HOUR(created_at) AS bucket, SUM(total) AS total
             FROM sales
             WHERE tenant_id = :tenant_id AND status = 'completed' AND DATE(created_at) = CURDATE()
             GROUP BY HOUR(created_at)"
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        $byHour = [];
        foreach ($stmt->fetchAll() as $row) {
            $byHour[(int) $row['bucket']] = (float) $row['total'];
        }

        $result = [];
        for ($h = 0; $h <= (int) date('G'); $h++) {
            $result[] = ['label' => sprintf('%02d:00', $h), 'total' => $byHour[$h] ?? 0.0];
        }

        return $result;
    }

    /**
     * Daily totals for the last $daysBack days up to today.
     * When $fromMonthStart is true, days are numbered from the 1st of the
     * current month instead of counting back from today.
     */
    private static function dailyTrend(int $tenantId, int $daysBack, bool $fromMonthStart = false): array
    {
        $startCondition = $fromMonthStart
            ? "created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"
            : "created_at >= (CURDATE() - INTERVAL $daysBack DAY)";

        $stmt = self::db()->prepare(
            "SELECT DATE(created_at) AS day, SUM(total) AS total
             FROM sales
             WHERE tenant_id = :tenant_id AND status = 'completed' AND $startCondition
             GROUP BY DATE(created_at)"
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        $byDate = [];
        foreach ($stmt->fetchAll() as $row) {
            $byDate[$row['day']] = (float) $row['total'];
        }

        $result = [];
        for ($i = $daysBack; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} day"));
            $label = $fromMonthStart ? date('j', strtotime($date)) : date('d/m', strtotime($date));
            $result[] = ['label' => (string) $label, 'total' => $byDate[$date] ?? 0.0];
        }

        return $result;
    }

    private static function monthlyTrend(int $tenantId): array
    {
        $stmt = self::db()->prepare(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, SUM(total) AS total
             FROM sales
             WHERE tenant_id = :tenant_id AND status = 'completed'
               AND created_at >= DATE_FORMAT(CURDATE(), '%Y-01-01')
             GROUP BY DATE_FORMAT(created_at, '%Y-%m')"
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        $byMonth = [];
        foreach ($stmt->fetchAll() as $row) {
            $byMonth[$row['ym']] = (float) $row['total'];
        }

        $monthNames = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        $result = [];
        for ($m = 1; $m <= (int) date('n'); $m++) {
            $ym = date('Y') . '-' . sprintf('%02d', $m);
            $result[] = ['label' => $monthNames[$m - 1], 'total' => $byMonth[$ym] ?? 0.0];
        }

        return $result;
    }
}
