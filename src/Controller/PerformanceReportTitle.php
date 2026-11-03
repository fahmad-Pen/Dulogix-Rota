<?php
namespace App\Controller;

final class PerformanceReportTitle
{
    public const DEFAULTS = [
        'dsp' => 'DSP WEEKLY SUMMARY',
        'cc' => 'Contact Compliance Report',
        'pod' => 'Photo on Delivery Quality Report',
        'dwc' => 'DWC/IADC Report',
    ];

    public static function matches(array $rows, string $title): bool
    {
        $normalize = static fn(string $s): string => trim(preg_replace('/[^A-Z0-9]+/', ' ', strtoupper($s)));
        $needle = $normalize($title);
        if ($needle === '') return false;
        // A title may be surrounded by depot and week identifiers, or split
        // over consecutive lines in an extracted PDF.
        $text = $normalize(implode(' ', array_map(
            static fn(array $row): string => implode(' ', array_map('strval', $row)), $rows
        )));
        return str_contains(' '.$text.' ', ' '.$needle.' ');
    }

    public static function requireTitle(array $rows, string $title, string $report): void
    {
        if (!self::matches($rows, $title)) {
            throw new \InvalidArgumentException('Incorrect report type. Expected '.$report.' report containing "'.$title.'". Existing data was not changed.');
        }
    }
}
