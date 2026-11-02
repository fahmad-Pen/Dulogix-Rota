<?php
namespace App\Controller;
use Symfony\Component\HttpFoundation\File\UploadedFile;
final class DspSummaryReader
{
    public function read(?UploadedFile $file, string $paste = ''): array
    {
        $rows = (new PerformanceReportReader())->read($file, $paste);
        $results = [];
        foreach ($rows as $cells) {
            $cells = array_map(static fn($v) => trim((string)$v), $cells);
            // Source has a serial number before the Transporter ID.
            $offset = count($cells) >= 11 && ctype_digit($cells[0]) ? 1 : 0;
            if (count($cells) < $offset + 10) continue;
            $id = strtoupper(preg_replace('/\s+/', '', $cells[$offset]));
            if (!preg_match('/^[A-Z0-9]{10,40}$/', $id) || !preg_match('/[A-Z]/', $id)) continue;
            $data = array_slice($cells, $offset + 1, 9);
            if (!preg_match('/^\d[\d,]*$/', $data[0])) continue;
            foreach ([1,4,5] as $index) {
                if (!preg_match('/^\d+(?:\.\d+)?%$/', $data[$index])) continue 2;
            }
            foreach ([2,3,6,7,8] as $index) {
                if ($data[$index] !== '-' && !preg_match('/^\d[\d,]*(?:\.\d+)?$/', $data[$index])) continue 2;
            }
            if (isset($results[$id])) throw new \InvalidArgumentException('Duplicate Transporter ID: '.$id);
            $results[$id] = $data;
        }
        if (!$results) throw new \InvalidArgumentException('No valid DSP Summary rows found. Expected Transporter ID, Delivered, DCR, DSC DPMO, LoR DPMO, POD, CC, CE, CDF DPMO, PSB.');
        return $results;
    }
}
