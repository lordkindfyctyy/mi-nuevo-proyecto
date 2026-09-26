<?php

require_once __DIR__ . '/../../config/database.php';

class Report
{
    public const PERIODS = ['day', 'yesterday', 'week', 'month', 'year', 'custom'];

    private static function db(): PDO
    {
        return getConnection();
    }

    public static function normalizePeriod(?string $period): string
    {
        return in_array($period, self::PERIODS, true) ? $period : 'day';
    }

    private static function parseDate(?string $value): ?DateTimeImmutable
    {
        if (!$value) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $date ?: null;
    }

    /**
     * "Now" according to the database server, not the PHP process. sales.
     * created_at is stamped by MySQL's own CURRENT_TIMESTAMP, and PHP's
     * timezone can drift from the DB server's (e.g. PHP on Europe/Berlin,
     * MySQL on system/UTC) — using PHP's own clock to compute "today"/"this
     * week" boundaries would silently exclude same-day sales in that case.
     */
    private static function dbNow(): DateTimeImmutable
    {
        $now = self::db()->query('SELECT NOW() AS now')->fetch()['now'];

        return new DateTimeImmutable($now);
    }

    /**
     * Resolves a period keyword (or an explicit custom start/end) into a
     * concrete datetime window plus a human label, so every metric on the
     * Balance page (totals, charts, sale list) always filters by the exact
     * same range. Falls back to "day" when a custom range is missing or
     * invalid instead of failing the page.
     *
     * @return array{period:string, start:string, end:string, label:string, startInput:string, endInput:string}
     */
    public static function resolveRange(string $period, ?string $customStart = null, ?string $customEnd = null): array
    {
        $period = self::normalizePeriod($period);
        $now = self::dbNow();

        if ($period === 'custom') {
            $start = self::parseDate($customStart);
            $end = self::parseDate($customEnd);
            if ($start && $end && $start <= $end) {
                return [
                    'period' => 'custom',
                    'start' => $start->format('Y-m-d 00:00:00'),
                    'end' => $end->format('Y-m-d 23:59:59'),
                    'label' => $start->format('d/m/Y') . ' - ' . $end->format('d/m/Y'),
                    'startInput' => $start->format('Y-m-d'),
                    'endInput' => $end->format('Y-m-d'),
                ];
            }
            $period = 'day';
        }

        if ($period === 'yesterday') {
            $yesterday = $now->modify('-1 day');

            return [
                'period' => 'yesterday',
                'start' => $yesterday->format('Y-m-d 00:00:00'),
                'end' => $yesterday->format('Y-m-d 23:59:59'),
                'label' => 'Ayer',
                'startInput' => $yesterday->format('Y-m-d'),
                'endInput' => $yesterday->format('Y-m-d'),
            ];
        }

        $dayOfWeek = (int) $now->format('N'); // 1 (lunes) .. 7 (domingo)
        $start = match ($period) {
            'week' => $now->modify('-' . ($dayOfWeek - 1) . ' days'),
            'month' => $now->modify('first day of this month'),
            'year' => new DateTimeImmutable($now->format('Y') . '-01-01'),
            default => $now,
        };
        $label = [
            'day' => 'Hoy',
            'week' => 'Esta semana',
            'month' => 'Este mes',
            'year' => 'Este año',
        ][$period];

        return [
            'period' => $period,
            'start' => $start->format('Y-m-d 00:00:00'),
            'end' => $now->format('Y-m-d H:i:s'),
            'label' => $label,
            'startInput' => $start->format('Y-m-d'),
            'endInput' => $now->format('Y-m-d'),
        ];
    }

    /**
     * Total facturado, cantidad de ventas y ticket promedio para el rango
     * dado: las tres métricas principales del encabezado del Balance.
     *
     * @param array{start:string, end:string} $range
     * @return array{count:int, total:float, average:float}
     */
    public static function summary(int $tenantId, array $range): array
    {
        $stmt = self::db()->prepare(
            "SELECT COUNT(*) AS sales_count, COALESCE(SUM(total), 0) AS total, COALESCE(AVG(total), 0) AS average
             FROM sales
             WHERE tenant_id = :tenant_id AND status = 'completed' AND created_at BETWEEN :start AND :end"
        );
        $stmt->execute(['tenant_id' => $tenantId, 'start' => $range['start'], 'end' => $range['end']]);
        $row = $stmt->fetch();

        return [
            'count' => (int) $row['sales_count'],
            'total' => (float) $row['total'],
            'average' => (float) $row['average'],
        ];
    }

    public static function topProduct(int $tenantId, array $range): ?array
    {
        $products = self::topProducts($tenantId, $range, 1);

        return $products[0] ?? null;
    }

    /**
     * @param array{start:string, end:string} $range
     */
    public static function topProducts(int $tenantId, array $range, int $limit = 5): array
    {
        $stmt = self::db()->prepare(
            "SELECT p.id, p.name, SUM(si.quantity) AS quantity_sold
             FROM sale_items si
             JOIN sales s ON s.id = si.sale_id
             JOIN products p ON p.id = si.product_id
             WHERE s.tenant_id = :tenant_id AND s.status = 'completed' AND s.created_at BETWEEN :start AND :end
             GROUP BY p.id, p.name
             ORDER BY quantity_sold DESC
             LIMIT " . max(1, $limit)
        );
        $stmt->execute(['tenant_id' => $tenantId, 'start' => $range['start'], 'end' => $range['end']]);

        return $stmt->fetchAll();
    }

    /**
     * Revenue, cost and margin for the given range. The cost side uses each
     * product's current cost (not a historical snapshot), since that is the
     * only cost data the schema tracks.
     *
     * @param array{start:string, end:string} $range
     */
    public static function margin(int $tenantId, array $range): array
    {
        $stmt = self::db()->prepare(
            "SELECT COALESCE(SUM(si.subtotal), 0) AS revenue, COALESCE(SUM(si.quantity * p.cost), 0) AS cost
             FROM sale_items si
             JOIN sales s ON s.id = si.sale_id
             JOIN products p ON p.id = si.product_id
             WHERE s.tenant_id = :tenant_id AND s.status = 'completed' AND s.created_at BETWEEN :start AND :end"
        );
        $stmt->execute(['tenant_id' => $tenantId, 'start' => $range['start'], 'end' => $range['end']]);
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
     * Sales totals bucketed by hour (single-day range) or by day/month
     * depending on how wide the range is, so a custom range of any length
     * still renders a sensible chart.
     *
     * @param array{period:string, start:string, end:string} $range
     * @return array<int, array{label: string, total: float}>
     */
    public static function salesTrend(int $tenantId, array $range): array
    {
        if ($range['period'] === 'day' || $range['period'] === 'yesterday') {
            return self::hourlyTrend($tenantId, $range);
        }

        $startDate = new DateTimeImmutable(substr($range['start'], 0, 10));
        $endDate = new DateTimeImmutable(substr($range['end'], 0, 10));
        $daySpan = (int) $startDate->diff($endDate)->days;

        return $daySpan <= 31
            ? self::dailyTrend($tenantId, $range)
            : self::monthlyTrend($tenantId, $range);
    }

    private static function hourlyTrend(int $tenantId, array $range): array
    {
        $stmt = self::db()->prepare(
            "SELECT HOUR(created_at) AS bucket, SUM(total) AS total
             FROM sales
             WHERE tenant_id = :tenant_id AND status = 'completed' AND created_at BETWEEN :start AND :end
             GROUP BY HOUR(created_at)"
        );
        $stmt->execute(['tenant_id' => $tenantId, 'start' => $range['start'], 'end' => $range['end']]);

        $byHour = [];
        foreach ($stmt->fetchAll() as $row) {
            $byHour[(int) $row['bucket']] = (float) $row['total'];
        }

        $currentHour = (int) (new DateTimeImmutable($range['end']))->format('G');
        $result = [];
        for ($h = 0; $h <= $currentHour; $h++) {
            $result[] = ['label' => sprintf('%02d:00', $h), 'total' => $byHour[$h] ?? 0.0];
        }

        return $result;
    }

    /**
     * Daily totals across every day in the range (inclusive on both ends).
     */
    private static function dailyTrend(int $tenantId, array $range): array
    {
        $stmt = self::db()->prepare(
            "SELECT DATE(created_at) AS day, SUM(total) AS total
             FROM sales
             WHERE tenant_id = :tenant_id AND status = 'completed' AND created_at BETWEEN :start AND :end
             GROUP BY DATE(created_at)"
        );
        $stmt->execute(['tenant_id' => $tenantId, 'start' => $range['start'], 'end' => $range['end']]);

        $byDate = [];
        foreach ($stmt->fetchAll() as $row) {
            $byDate[$row['day']] = (float) $row['total'];
        }

        $result = [];
        $cursor = new DateTimeImmutable(substr($range['start'], 0, 10));
        $endDate = new DateTimeImmutable(substr($range['end'], 0, 10));
        while ($cursor <= $endDate) {
            $key = $cursor->format('Y-m-d');
            $result[] = ['label' => $cursor->format('d/m'), 'total' => $byDate[$key] ?? 0.0];
            $cursor = $cursor->modify('+1 day');
        }

        return $result;
    }

    /**
     * Monthly totals across every month in the range (inclusive on both ends).
     */
    private static function monthlyTrend(int $tenantId, array $range): array
    {
        $stmt = self::db()->prepare(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, SUM(total) AS total
             FROM sales
             WHERE tenant_id = :tenant_id AND status = 'completed' AND created_at BETWEEN :start AND :end
             GROUP BY DATE_FORMAT(created_at, '%Y-%m')"
        );
        $stmt->execute(['tenant_id' => $tenantId, 'start' => $range['start'], 'end' => $range['end']]);

        $byMonth = [];
        foreach ($stmt->fetchAll() as $row) {
            $byMonth[$row['ym']] = (float) $row['total'];
        }

        $monthNames = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        $result = [];
        $cursor = (new DateTimeImmutable(substr($range['start'], 0, 10)))->modify('first day of this month');
        $endCursor = (new DateTimeImmutable(substr($range['end'], 0, 10)))->modify('first day of this month');
        while ($cursor <= $endCursor) {
            $ym = $cursor->format('Y-m');
            $result[] = ['label' => $monthNames[(int) $cursor->format('n') - 1], 'total' => $byMonth[$ym] ?? 0.0];
            $cursor = $cursor->modify('first day of next month');
        }

        return $result;
    }
}
