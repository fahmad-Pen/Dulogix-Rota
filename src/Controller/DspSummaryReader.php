<?php
namespace App\Controller;

use Symfony\Component\HttpFoundation\File\UploadedFile;

final class DspSummaryReader
{
    public function read(?UploadedFile $file, string $paste = '', string $requiredTitle = 'DSP WEEKLY SUMMARY'): array
    {
        $rows = (new PerformanceReportReader())->read($file, $paste);
        // Preserve the original row-based extraction, which works even when
        // PDF headings are split across columns or lines.
        PerformanceReportTitle::requireTitle($rows, $requiredTitle, 'DSP Daily Summary');
        $sectionStart = null;
        foreach ($rows as $index => $cells) {
            if (PerformanceReportTitle::matches([$cells], $requiredTitle)) {
                $sectionStart = $index;
                break;
            }
        }
        // Portable PDF extractors may preserve only single spaces (or put
        // individual cells on separate lines). Reconstruct numbered DSP table
        // rows from their strongly typed columns rather than visual spacing.
        if ($file && strtolower($file->getClientOriginalExtension()) === 'pdf') {
            $plainText = implode("\n", array_map(
                static fn(array $cells): string => implode(' ', array_map('strval', $cells)),
                array_slice($rows, ($sectionStart ?? -1) + 1)
            ));
            $pattern = '/(?:^|\n)[ \t]*(\d{1,4})\s+([A-Z0-9]{10,40})((?:\s+[\d,.%\-]+){9})(?=\s|$)/m';
            preg_match_all($pattern, $plainText, $matches, PREG_SET_ORDER);
            if (count($matches) >= 5) {
                $reconstructed = [];
                foreach ($matches as $match) {
                    $number = (int) $match[1];
                    // Reject incomplete or out-of-order extraction, never
                    // silently import a subset of a weekly report.
                    if ($number !== count($reconstructed) + 1) {
                        throw new \InvalidArgumentException('DSP PDF driver rows are incomplete or out of order. No data was changed.');
                    }
                    $reconstructed[] = array_merge(
                        [$match[1], $match[2]],
                        preg_split('/\s+/', trim($match[3])) ?: []
                    );
                }
                $rows = $reconstructed;
                $sectionStart = null;
            }
        }
        $results = [];
        $started = false;
        foreach ($rows as $index => $cells) {
            if ($sectionStart !== null && $index <= $sectionStart) continue;
            $cells = array_map(static fn($v) => trim((string)$v), $cells);
            $offset = count($cells) >= 11 && ctype_digit($cells[0]) ? 1 : 0;
            if (count($cells) < $offset + 10) continue;
            $id = strtoupper(preg_replace('/\s+/', '', $cells[$offset]));
            if (!preg_match('/^[A-Z0-9]{10,40}$/D', $id) || !preg_match('/[A-Z]/', $id) || !preg_match('/[0-9]/', $id)) continue;
            $data = array_map(static fn($v) => in_array(trim((string)$v), ['', '-'], true) ? '0' : $v, array_slice($cells, $offset + 1, 9));
            if (!preg_match('/^\d[\d,]*$/D', $data[0])) continue;
            $valid = true;
            foreach ([1,4,5] as $i) if (!preg_match('/^\d+(?:\.\d+)?%?$/D', $data[$i])) $valid = false;
            foreach ([2,3,6,7,8] as $i) if (!preg_match('/^\d[\d,]*(?:\.\d+)?$/D', $data[$i])) $valid = false;
            if (!$valid) continue;
            if (isset($results[$id])) throw new \InvalidArgumentException('Duplicate Transporter ID: '.$id);
            $results[$id] = $data;
            $started = true;
        }
        // Prevent accidental partial imports of unrelated reports.
        if (!$started || count($results) < 5) {
            throw new \InvalidArgumentException('This does not look like a DSP Weekly Summary driver table. No data was changed.');
        }
        return $results;
    }
}
