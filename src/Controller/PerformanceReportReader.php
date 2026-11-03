<?php
namespace App\Controller;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/** Shared input extraction for Performance Hub report tabs. */
final class PerformanceReportReader
{
    public function read(?UploadedFile $file, string $paste = ''): array
    {
        if ($file) {
            if (!$file->isValid()) throw new \InvalidArgumentException('File upload failed.');
            if ($file->getSize() > 20000000) throw new \InvalidArgumentException('File exceeds 20 MB.');
            $ext = strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
            if (!in_array($ext, ['mhtml','mht','html','htm','csv','txt','xlsx','pdf'], true)) throw new \InvalidArgumentException('Supported: PDF, XLSX, MHTML, HTML, CSV and text.');
            if ($ext === 'xlsx') return (new PodReportReader())->xlsxRows($file->getPathname());
            if ($ext === 'pdf') {
                $stream = fopen($file->getPathname(), 'rb');
                $signature = $stream ? fread($stream, 5) : '';
                if ($stream) fclose($stream);
                if ($signature !== '%PDF-') throw new \InvalidArgumentException('Invalid PDF file.');
                // Prefer Poppler on both local Docker and IONOS shared hosting.
                // IONOS uses a private ARM64 installation with its own libraries.
                $lines = [];
                $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
                $configuredBinary = getenv('PDFTOTEXT_BINARY') ?: '';
                $configuredLibraryPath = getenv('PDFTOTEXT_LIBRARY_PATH') ?: '';
                $binaries = array_values(array_unique(array_filter([
                    $configuredBinary,
                    '/home/www/poppler-local/usr/bin/pdftotext',
                    '/usr/bin/pdftotext',
                    '/usr/local/bin/pdftotext',
                    '/bin/pdftotext',
                ])));
                if (function_exists('exec') && !in_array('exec', $disabled, true)) {
                    foreach ($binaries as $binary) {
                        if (!is_file($binary) || !is_executable($binary)) continue;
                        $libraryPath = $configuredLibraryPath;
                        if ($libraryPath === '' && $binary === '/home/www/poppler-local/usr/bin/pdftotext') {
                            $libraryPath = '/home/www/poppler-local/usr/lib/aarch64-linux-gnu';
                        }
                        $command = '';
                        if ($libraryPath !== '') {
                            // Use an environment assignment scoped to this process only.
                            $command .= 'LD_LIBRARY_PATH='.escapeshellarg($libraryPath).' ';
                        }
                        $command .= escapeshellarg($binary).' -layout -nopgbrk '
                            .escapeshellarg($file->getPathname()).' - 2>/dev/null';
                        $exit = 0;
                        exec($command, $lines, $exit);
                        if ($exit === 0 && trim(implode('', $lines)) !== '') break;
                        $lines = [];
                    }
                }
                if (!$lines) {
                    try {
                        $text = (new \Smalot\PdfParser\Parser())->parseFile($file->getPathname())->getText();
                        if (trim($text) === '') throw new \RuntimeException('No selectable text');
                        $lines = preg_split('/\r\n|\n|\r/', $text) ?: [];
                    } catch (\Throwable $error) {
                        throw new \InvalidArgumentException('Could not extract text from this PDF. Check that it contains selectable text (not scanned images).', 0, $error);
                    }
                }
                return array_map(static fn($line) => preg_split('/\s{2,}/', trim($line)) ?: [], $lines);
            }
            $raw = (string)file_get_contents($file->getPathname());
            if (in_array($ext, ['mhtml','mht'], true)) $raw = $this->extractMhtml($raw);
        } else {
            $raw = $paste;
        }
        if (trim($raw) === '') throw new \InvalidArgumentException('Choose a report file or paste the table.');
        if (strlen($raw) > 20000000) throw new \InvalidArgumentException('Report content exceeds 20 MB.');
        if (stripos($raw, '<tr') === false) {
            $rows = [];
            foreach (preg_split('/\r\n|\n|\r/', $raw) as $line) {
                if (trim($line) !== '') $rows[] = str_getcsv($line, str_contains($line, "\t") ? "\t" : ',');
            }
            return $rows;
        }
        $dom = new \DOMDocument();
        $prior = libxml_use_internal_errors(true);
        try {
            $dom->loadHTML('<?xml encoding="utf-8" ?>'.$raw, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($prior);
        }
        $rows = [];
        // Keep report headings outside tables for report-type validation.
        // These rows do not contain transporter IDs and are ignored by data parsers.
        foreach (['title', 'h1', 'h2', 'h3', 'caption'] as $tag) {
            foreach ($dom->getElementsByTagName($tag) as $heading) {
                $headingText = trim(preg_replace('/\\s+/', ' ', $heading->textContent));
                if ($headingText !== '') $rows[] = [$headingText];
            }
        }
        foreach ($dom->getElementsByTagName('tr') as $tr) {
            $cells = [];
            foreach ($tr->childNodes as $node) {
                if ($node instanceof \DOMElement && in_array(strtolower($node->tagName), ['td','th'], true)) $cells[] = trim($node->textContent);
            }
            if ($cells) $rows[] = $cells;
        }
        return $rows;
    }

    private function extractMhtml(string $source): string
    {
        $source = str_replace("\r\n", "\n", $source);
        if (preg_match('/boundary\s*=\s*"?([^";\n]+)"?/i', $source, $match)) {
            $parts = explode('--'.trim($match[1]), $source);
        } else {
            $parts = preg_split('/\n(?=Content-Type:\s*text\/html)/i', $source) ?: [];
        }
        foreach ($parts as $part) {
            $pieces = explode("\n\n", ltrim($part, "\n"), 2);
            if (count($pieces) !== 2 || !preg_match('/Content-Type:\s*text\/html\b/i', $pieces[0])) continue;
            [$header,$body] = $pieces;
            if (preg_match('/Content-Transfer-Encoding:\s*base64/i', $header)) {
                $body = base64_decode(preg_replace('/\s+/', '', $body), true) ?: '';
            } elseif (preg_match('/Content-Transfer-Encoding:\s*quoted-printable/i', $header)) {
                $body = quoted_printable_decode($body);
            }
            if (stripos($body, '<tr') !== false) return $body;
        }
        foreach ([$source, quoted_printable_decode($source)] as $candidate) {
            if (stripos($candidate, '<tr') !== false) return $candidate;
        }
        throw new \InvalidArgumentException('No readable HTML table found in the MHTML report.');
    }
}
