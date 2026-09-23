<?php
require_once __DIR__ . '/../includes/tenant_context.php';
requireLogin();

/**
 * Genera un .xlsx mínimo válido (sin depender de ninguna librería de
 * escritura) con el encabezado esperado por product_import_process.php y
 * una fila de ejemplo, para que el comerciante tenga una plantilla lista
 * para completar.
 */

function xlsx_cell_xml(string $ref, string $value): string
{
    $escaped = htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');

    return '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . $escaped . '</t></is></c>';
}

function xlsx_row_xml(int $rowNumber, array $values): string
{
    $cols = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
    $cells = '';
    foreach ($values as $i => $value) {
        $cells .= xlsx_cell_xml($cols[$i] . $rowNumber, (string) $value);
    }

    return '<row r="' . $rowNumber . '">' . $cells . '</row>';
}

$header = ['Nombre', 'SKU', 'Categoría', 'Marca', 'Precio', 'Costo', 'Stock', 'Descripción'];
$example = ['Alimento Perro Adulto 15kg', 'ALIM-001', 'Alimento balanceado', 'Pro Plan', '45000', '32000', '10', 'Fila de ejemplo, la podés borrar'];

$sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
    . '<sheetData>'
    . xlsx_row_xml(1, $header)
    . xlsx_row_xml(2, $example)
    . '</sheetData>'
    . '</worksheet>';

$contentTypesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
    . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
    . '<Default Extension="xml" ContentType="application/xml"/>'
    . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
    . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
    . '</Types>';

$rootRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
    . '</Relationships>';

$workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
    . '<sheets><sheet name="Productos" sheetId="1" r:id="rId1"/></sheets>'
    . '</workbook>';

$workbookRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
    . '</Relationships>';

$tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_');
$zip = new ZipArchive();
$zip->open($tmpFile, ZipArchive::OVERWRITE);
$zip->addEmptyDir('_rels');
$zip->addEmptyDir('xl');
$zip->addEmptyDir('xl/_rels');
$zip->addEmptyDir('xl/worksheets');
$zip->addFromString('[Content_Types].xml', $contentTypesXml);
$zip->addFromString('_rels/.rels', $rootRelsXml);
$zip->addFromString('xl/workbook.xml', $workbookXml);
$zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRelsXml);
$zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
$zip->close();

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="plantilla-productos.xlsx"');
header('Content-Length: ' . filesize($tmpFile));
readfile($tmpFile);
unlink($tmpFile);
exit;
