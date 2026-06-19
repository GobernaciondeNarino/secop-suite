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
}
