<?php

// COD-WEB 11.4: cobertura de líneas ejecutables de servicios y modelos.
$path = $argv[1] ?? 'storage/coverage/clover.xml';
$minimum = isset($argv[2]) ? filter_var($argv[2], FILTER_VALIDATE_FLOAT) : 70.0;
if ($minimum === false || $minimum < 70 || $minimum > 100 || ! is_file($path)) {
    fwrite(STDERR, "Informe ausente o umbral inválido.\n");
    exit(2);
}
$document = simplexml_load_file($path);
$metrics = $document?->project?->metrics;
$statements = (int) ($metrics['statements'] ?? 0);
$covered = (int) ($metrics['coveredstatements'] ?? 0);
if ($statements === 0) {
    fwrite(STDERR, "El informe no contiene líneas ejecutables.\n");
    exit(2);
}
$percentage = 100 * $covered / $statements;
printf("Servicios y modelos: %d/%d líneas = %.2f%%; mínimo %.2f%%.\n", $covered, $statements, $percentage, $minimum);
exit($percentage >= $minimum ? 0 : 1);
