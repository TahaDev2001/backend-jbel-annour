<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class XlsxExporter
{
    private const WIDTHS = [18, 13, 22, 16, 13, 20, 34, 12, 14, 14, 14, 9, 16, 14, 18, 26, 28, 14];

    public static function download(array $headers, array $rows, array $moneyCols, string $filename): BinaryFileResponse
    {
        $numCols = count($headers);

        $sheetRows = '<row r="1" ht="22" customHeight="1">';
        foreach ($headers as $i => $h) {
            $col = self::colLetter($i + 1);
            $sheetRows .= '<c r="' . $col . '1" t="inlineStr" s="1"><is><t xml:space="preserve">' . self::esc($h) . '</t></is></c>';
        }
        $sheetRows .= '</row>';

        $rIdx = 2;
        foreach (array_values($rows) as $rowNum => $row) {
            $zebra = ($rowNum % 2 === 1);
            $sheetRows .= '<row r="' . $rIdx . '">';
            foreach (array_values($row) as $ci => $val) {
                $col = self::colLetter($ci + 1);
                if (in_array($ci, $moneyCols, true)) {
                    $style = $zebra ? 5 : 3;
                    $num = is_numeric($val) ? $val : 0;
                    $sheetRows .= '<c r="' . $col . $rIdx . '" s="' . $style . '"><v>' . $num . '</v></c>';
                } else {
                    $style = $zebra ? 4 : 2;
                    $sheetRows .= '<c r="' . $col . $rIdx . '" t="inlineStr" s="' . $style . '"><is><t xml:space="preserve">' . self::esc($val) . '</t></is></c>';
                }
            }
            $sheetRows .= '</row>';
            $rIdx++;
        }

        $lastRow = max($rIdx - 1, 1);
        $lastCol = self::colLetter($numCols);

        $colsXml = '<cols>';
        for ($i = 0; $i < $numCols; $i++) {
            $w = self::WIDTHS[$i] ?? 15;
            $colsXml .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $w . '" customWidth="1"/>';
        }
        $colsXml .= '</cols>';

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<dimension ref="A1:' . $lastCol . $lastRow . '"/>'
            . '<sheetViews><sheetView tabSelected="1" workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="16"/>'
            . $colsXml
            . '<sheetData>' . $sheetRows . '</sheetData>'
            . '<autoFilter ref="A1:' . $lastCol . '1"/>'
            . '</worksheet>';

        $stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.00"/></numFmts>'
            . '<fonts count="2">'
            . '<font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="4">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF198754"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFF3F4F6"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="2">'
            . '<border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border><left style="thin"><color rgb="FFD9D9D9"/></left><right style="thin"><color rgb="FFD9D9D9"/></right><top style="thin"><color rgb="FFD9D9D9"/></top><bottom style="thin"><color rgb="FFD9D9D9"/></bottom><diagonal/></border>'
            . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="6">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>'
            . '<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/>'
            . '<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFill="1" applyBorder="1"/>'
            . '<xf numFmtId="164" fontId="0" fillId="3" borderId="1" xfId="0" applyNumberFormat="1" applyFill="1" applyBorder="1"/>'
            . '</cellXfs>'
            . '</styleSheet>';

        $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Factures" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';

        $workbookRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';

        $rootRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';

        $contentTypesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';

        $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx');

        $zip = new ZipArchive();
        $zip->open($tmpFile, ZipArchive::OVERWRITE);
        $zip->addFromString('_rels/.rels', $rootRelsXml);
        $zip->addFromString('[Content_Types].xml', $contentTypesXml);
        $zip->addFromString('xl/workbook.xml', $workbookXml);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRelsXml);
        $zip->addFromString('xl/styles.xml', $stylesXml);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->close();

        return response()
            ->download($tmpFile, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'max-age=0',
            ])
            ->deleteFileAfterSend(true);
    }

    private static function colLetter(int $num): string
    {
        $letter = '';
        while ($num > 0) {
            $mod = ($num - 1) % 26;
            $letter = chr(65 + $mod) . $letter;
            $num = intdiv($num - 1, 26);
        }

        return $letter;
    }

    private static function esc($value): string
    {
        return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], (string) $value);
    }
}