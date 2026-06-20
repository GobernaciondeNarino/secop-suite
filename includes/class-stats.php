<?php
/**
 * Stats — utilidades estadísticas y de formato PURAS (sin WordPress).
 * Diseñado para ser testeable de forma aislada.
 *
 * @package SecopSuite
 */
declare(strict_types=1);

namespace SecopSuite;

final class Stats
{
    /** Año (vigencia) de una fecha 'Y-m-d' o 'Y-m-d H:i:s'. */
    public static function vigencia_from_date(string $date): int
    {
        return (int) substr(trim($date), 0, 4);
    }

    /**
     * Meses transcurridos de una vigencia a una fecha de referencia.
     * 0 si la vigencia es futura; 12 si ya terminó.
     */
    public static function meses_transcurridos(string $ref_date, int $vigencia): int
    {
        $ref_year  = self::vigencia_from_date($ref_date);
        $ref_month = (int) substr(trim($ref_date), 5, 2);
        if ($ref_year < $vigencia) return 0;
        if ($ref_year > $vigencia) return 12;
        return max(0, min(12, $ref_month));
    }

    /**
     * Regresión lineal por mínimos cuadrados.
     * @param array $points lista de [x, y] (numéricos).
     * @return array{slope:?float,intercept:?float,r2:?float,se:?float,n:int,insufficient:bool}
     */
    public static function linear_regression(array $points): array
    {
        $n = count($points);
        if ($n < 2) {
            return ['slope' => null, 'intercept' => null, 'r2' => null, 'se' => null, 'n' => $n, 'insufficient' => true];
        }
        $sx = $sy = $sxx = $sxy = $syy = 0.0;
        foreach ($points as [$x, $y]) {
            $x = (float) $x; $y = (float) $y;
            $sx += $x; $sy += $y; $sxx += $x * $x; $sxy += $x * $y; $syy += $y * $y;
        }
        $denom = ($n * $sxx) - ($sx * $sx);
        if ($denom == 0.0) {
            return ['slope' => null, 'intercept' => null, 'r2' => null, 'se' => null, 'n' => $n, 'insufficient' => true];
        }
        $slope     = (($n * $sxy) - ($sx * $sy)) / $denom;
        $intercept = ($sy - ($slope * $sx)) / $n;

        // R² = (cov^2) / (var_x * var_y)
        $ss_tot = $syy - ($sy * $sy) / $n;
        $ss_res = 0.0;
        foreach ($points as [$x, $y]) {
            $pred = $slope * (float) $x + $intercept;
            $ss_res += (((float) $y) - $pred) ** 2;
        }
        $r2 = $ss_tot > 0 ? max(0.0, 1.0 - ($ss_res / $ss_tot)) : 1.0;

        // Error estándar de la estimación (incertidumbre).
        $se = $n > 2 ? sqrt($ss_res / ($n - 2)) : 0.0;

        return ['slope' => $slope, 'intercept' => $intercept, 'r2' => $r2, 'se' => $se, 'n' => $n, 'insufficient' => false];
    }

    /** Proyectar y para un x dado a partir de un resultado de regresión. */
    public static function project(array $reg, float $x): ?float
    {
        if ($reg['insufficient'] || $reg['slope'] === null) return null;
        return $reg['slope'] * $x + $reg['intercept'];
    }

    public static function median(array $values): float
    {
        $values = array_values(array_map('floatval', $values));
        sort($values);
        $n = count($values);
        if ($n === 0) return 0.0;
        $mid = intdiv($n, 2);
        return $n % 2 ? $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2;
    }

    public static function mean(array $values): float
    {
        $n = count($values);
        return $n ? array_sum(array_map('floatval', $values)) / $n : 0.0;
    }

    /** Coeficiente de variación (desv. estándar poblacional / media). */
    public static function cv(array $values): float
    {
        $n = count($values);
        if ($n === 0) return 0.0;
        $mean = self::mean($values);
        if ($mean == 0.0) return 0.0;
        $var = 0.0;
        foreach ($values as $v) { $var += ((float) $v - $mean) ** 2; }
        $var /= $n;
        return sqrt($var) / abs($mean);
    }

    /**
     * Índice Herfindahl-Hirschman NORMALIZADO de concentración [0..1].
     * 1 = monopolio (una sola categoría); 0 = reparto perfectamente equitativo.
     */
    public static function hhi(array $values): float
    {
        $values = array_map('floatval', array_filter($values, fn($v) => (float) $v > 0));
        $n = count($values);
        if ($n <= 1) return $n === 1 ? 1.0 : 0.0;
        $total = array_sum($values);
        if ($total == 0.0) return 0.0;
        $h = 0.0;
        foreach ($values as $v) { $h += ($v / $total) ** 2; }
        return max(0.0, ($h - 1 / $n) / (1 - 1 / $n)); // normalización
    }

    /** Participación acumulada de las `k` categorías mayores [0..1]. */
    public static function top_share(array $values, int $k = 1): float
    {
        $values = array_map('floatval', $values);
        $total  = array_sum($values);
        if ($total == 0.0) return 0.0;
        rsort($values);
        return array_sum(array_slice($values, 0, max(1, $k))) / $total;
    }

    /** Moneda colombiana: separador de miles '.', sin decimales. */
    public static function money(float $value): string
    {
        return '$' . number_format(round($value), 0, ',', '.');
    }

    /** Porcentaje es-CO; admite [0..1]. Quita decimal '0'. */
    public static function percent(float $ratio): string
    {
        $p = $ratio * 100;
        $s = number_format($p, 1, ',', '.');
        $s = preg_replace('/,0$/', '', $s);
        return $s . '%';
    }

    /** Número entero formateado es-CO. */
    public static function num(float $value): string
    {
        return number_format(round($value), 0, ',', '.');
    }

    /** Recorta a <=564 caracteres respetando límites de palabra. */
    public static function clamp564(string $text): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));
        if (mb_strlen($text) <= 564) return $text;
        $cut = mb_substr($text, 0, 564);
        $sp  = mb_strrpos($cut, ' ');
        if ($sp !== false && $sp > 0) $cut = mb_substr($cut, 0, $sp);
        return rtrim($cut, " ,.;:") . '…';
    }
}
