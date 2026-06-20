<?php
use SecopSuite\Stats;

function ds(): array {
    return [
        'dimension' => 'dependencia', 'vigencia' => 2026, 'meses' => 6,
        'categorias' => [
            ['label' => 'Salud',    'valor' => 900.0, 'conteo' => 12],
            ['label' => 'Educación','valor' => 400.0, 'conteo' => 10],
            ['label' => 'Vías',     'valor' => 200.0, 'conteo' => 8],
        ],
        'serie_mensual' => [[1,100.0],[2,250.0],[3,500.0],[4,800.0],[5,1200.0],[6,1500.0]],
        'total_valor' => 1500.0, 'total_conteo' => 30,
        'ejecutado' => 900.0, 'saldo' => 600.0,
    ];
}

it('descripcion menciona vigencia y totales y respeta 564', function () {
    $t = Stats::analisis_descripcion(ds());
    assert_true(str_contains($t, '2026'));
    assert_true(str_contains($t, '30'));
    assert_true(mb_strlen($t) <= 564);
});
it('cualitativo menciona la categoria dominante', function () {
    $t = Stats::analisis_cualitativo(ds());
    assert_true(str_contains($t, 'Salud'));
    assert_true(mb_strlen($t) <= 564);
});
it('cuantitativo incluye cifras y % ejecucion', function () {
    $t = Stats::analisis_cuantitativo(ds());
    assert_true(str_contains($t, '$'));
    assert_true(mb_strlen($t) <= 564);
});
it('prediccion proyecta cierre con R2', function () {
    $t = Stats::analisis_prediccion(ds());
    assert_true(str_contains($t, 'R²') || str_contains($t, 'ajuste'));
    assert_true(mb_strlen($t) <= 564);
});
it('prediccion con datos insuficientes lo advierte', function () {
    $d = ds(); $d['serie_mensual'] = [[1,100.0]];
    $t = Stats::analisis_prediccion($d);
    assert_true(str_contains(mb_strtolower($t), 'insuficien') || str_contains(mb_strtolower($t), 'no es posible'));
});
