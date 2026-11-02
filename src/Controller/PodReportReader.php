<?php
namespace App\Controller;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/** Extracts POD driver rows from PDF, HTML/MHTML, CSV, XLSX, or pasted text. */
final class PodReportReader
{
    public function read(?UploadedFile $file, string $paste = '', string $requiredTitle = 'Photo on Delivery Quality Report'): array
    {
        if ($file && (!$file->isValid() || $file->getSize() > 20000000)) throw new \InvalidArgumentException('Upload failed or file exceeds 20 MB.');
        $ext = $file ? strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION)) : '';
        if ($file && !in_array($ext, ['pdf','html','htm','mhtml','mht','csv','txt','xlsx'], true)) throw new \InvalidArgumentException('Supported: PDF, HTML, MHTML, XLSX, CSV, TXT.');
        // Use the same extraction path as the working DSP Summary importer.
        $rawRows = (new PerformanceReportReader())->read($file, $paste);
        PerformanceReportTitle::requireTitle($rawRows, $requiredTitle, 'POD');
        // As with DSP, validate the configured report title and driver-row structure.
        // PDF/HTML exports often split or omit visible column headings.
        $rows = [];
        foreach ($rawRows as $cells) {
            $cells = array_values(array_map(static fn($v) => trim((string)$v), $cells));
            // PDF can use single spaces between numeric columns.
            if (count($cells) === 1) $cells = preg_split('/\s+/', $cells[0]) ?: [];
            if (count($cells) < 10) continue;
            foreach ($cells as $i => $candidate) {
                $id = strtoupper(preg_replace('/\s+/', '', $candidate));
                if (!preg_match('/^[A-Z0-9]{10,40}$/', $id) || !preg_match('/[A-Z]/', $id)) continue;
                $values = array_slice($cells, $i + 1, 9);
                if (count($values) !== 9 || count(array_filter($values, static fn($v) => in_array($v, ['', '-'], true) || preg_match('/^\d+(?:,\d{3})*$/', $v))) !== 9) continue;
                $values = array_map(static fn($v) => in_array($v, ['', '-'], true) ? 0 : (int)str_replace(',', '', $v), $values);
                if ($values[1] + $values[2] + $values[3] > $values[0]) continue;
                if (isset($rows[$id])) throw new \InvalidArgumentException('Duplicate Transporter ID in report: '.$id);
                $rows[$id] = $values;
                break;
            }
        }
        if (!$rows) throw new \InvalidArgumentException('No valid POD driver rows found. Check that the file contains selectable table data and the nine POD columns.');
        return $rows;
    }

    public function xlsxRows(string $path): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) throw new \InvalidArgumentException('Cannot open XLSX workbook.');
        try {
            $strings = [];
            $shared = $zip->getFromName('xl/sharedStrings.xml');
            if ($shared !== false) {
                $xml = simplexml_load_string($shared, 'SimpleXMLElement', LIBXML_NONET);
                if ($xml === false) throw new \InvalidArgumentException('Invalid XLSX shared strings.');
                foreach ($xml->si as $item) {
                    $text = (string)$item->t;
                    foreach ($item->r as $run) $text .= (string)$run->t;
                    $strings[] = $text;
                }
            }
            $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
            if ($sheet === false) throw new \InvalidArgumentException('Cannot find the first worksheet.');
            $xml = simplexml_load_string($sheet, 'SimpleXMLElement', LIBXML_NONET);
            if ($xml === false) throw new \InvalidArgumentException('Invalid XLSX worksheet.');
            $rows = [];
            foreach ($xml->sheetData->row as $row) {
                $cells = [];
                foreach ($row->c as $cell) {
                    $ref = (string)$cell['r'];
                    preg_match('/^[A-Z]+/', $ref, $match);
                    $index = 0;
                    foreach (str_split($match[0] ?? '') as $letter) $index = $index * 26 + ord($letter) - 64;
                    if ($index < 1 || $index > 100) continue;
                    $value = (string)$cell->v;
                    if ((string)$cell['t'] === 's') $value = $strings[(int)$value] ?? '';
                    elseif ((string)$cell['t'] === 'inlineStr') $value = (string)$cell->is->t;
                    $cells[$index - 1] = $value;
                }
                if ($cells) {
                    ksort($cells);
                    $rows[] = array_replace(array_fill(0, max(array_keys($cells)) + 1, ''), $cells);
                }
            }
            return $rows;
        } finally { $zip->close(); }
    }
}
