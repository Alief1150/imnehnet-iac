<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

/**
 * Menulis log aktivitas ke berkas Excel (.xlsx) atau CSV menggunakan OpenSpout.
 *
 * Setiap sel teks ditulis sebagai StringCell secara eksplisit agar nilai yang
 * diawali "=" tetap menjadi teks, bukan dirumuskan oleh Excel.
 *
 * Berkas ditulis di storage/app/exports/activity-logs/ yang dimiliki www-data
 * (PHP-FPM), sehingga web server dapat membuat dan membaca berkas tanpa
 * masalah permission. Lokasi cadangan adalah direktori sistem temp.
 */
final class ActivityLogExcelWriter
{
    /** @var list<string> */
    private const COLUMNS = [
        'When',
        'Action',
        'Actor',
        'Description',
        'Result',
        'Resource',
        'Source IP',
    ];

    /**
     * Lokasi utama: storage/app/exports/activity-logs/ (dimiliki www-data).
     */
    private const EXPORT_DIRECTORY = 'exports/activity-logs';

    /**
     * Lokasi cadangan jika storage/app/exports tidak dapat ditulis.
     */
    private const FALLBACK_DIRECTORY = 'imnehnet-exports/activity-logs';

    /**
     * Tulis semua catatan dari query ke berkas dan kembalikan jalurnya.
     *
     * @param  Builder<ActivityLog>  $query
     * @param  string  $format  'xlsx' atau 'csv'
     * @return string Jalur lengkap berkas yang telah ditulis
     *
     * @throws \RuntimeException jika gagal membuat berkas
     */
    public function write(Builder $query, string $format = 'xlsx'): string
    {
        $this->constrainToCurrentRecords($query);

        $extension = $format === 'csv' ? 'csv' : 'xlsx';
        $filePath = $this->temporaryFilePath($extension);

        if ($format === 'csv') {
            $this->writeCsv($query, $filePath);
        } else {
            $this->writeXlsx($query, $filePath);
        }

        // Pastikan direktori dan berkas readable oleh web server (www-data).
        $this->ensureFileReadable($filePath);

        if (! file_exists($filePath) || filesize($filePath) === 0) {
            throw new \RuntimeException("Berkas ekspor tidak berhasil dibuat: {$filePath}.");
        }

        return $filePath;
    }

    /**
     * Exclude records created after the export query begins.
     *
     * @param  Builder<ActivityLog>  $query
     */
    private function constrainToCurrentRecords(Builder $query): void
    {
        $maxId = (clone $query)->max('id');

        if ($maxId !== null) {
            $query->where('id', '<=', $maxId);
        }
    }

    /**
     * @param  Builder<ActivityLog>  $query
     */
    private function writeXlsx(Builder $query, string $filePath): void
    {
        $writer = new XlsxWriter;
        $writer->openToFile($filePath);

        // Baris 1: judul dokumen.
        $writer->addRow(Row::fromValues([
            'IMNEHNET Activity Log Export',
            '', '', '', '', '', '',
        ]));

        // Baris 2: header kolom dengan gaya tebal dan latar abu-pekat.
        $headerStyle = (new Style)
            ->setFontBold()
            ->setBackgroundColor('#1F2937');

        $headerRow = new Row([]);
        foreach (self::COLUMNS as $column) {
            $cell = new StringCell($column, null);
            $cell->setStyle($headerStyle);
            $headerRow->addCell($cell);
        }
        $headerRow->setStyle($headerStyle);
        $writer->addRow($headerRow);

        // Baris data, dibaca bertahap (500 per kali).
        foreach ($query->with('actor')->lazy(500) as $log) {
            $writer->addRow($this->makeDataRow($log));
        }

        $writer->close();
    }

    /**
     * @param  Builder<ActivityLog>  $query
     */
    private function writeCsv(Builder $query, string $filePath): void
    {
        $writer = new CsvWriter;
        $writer->openToFile($filePath);

        $writer->addRow(Row::fromValues(self::COLUMNS));

        foreach ($query->with('actor')->lazy(500) as $log) {
            $writer->addRow($this->makeDataRow($log));
        }

        $writer->close();
    }

    private function makeDataRow(ActivityLog $log): Row
    {
        $createdAt = Carbon::parse($log->created_at)
            ->setTimezone('Asia/Jakarta')
            ->format('d M Y H:i');

        $resource = $log->resource_type === null
            ? '—'
            : class_basename($log->resource_type);

        return Row::fromValues([
            $createdAt,
            $log->action ?? '—',
            $log->actor?->name ?? 'System',
            $log->description ?? '—',
            $log->status ?? '—',
            $resource,
            $log->ip_address ?? '—',
        ]);
    }

    /**
     * Buat jalur berkas unik. Coba storage/app/exports/activity-logs/ dulu
     * (dimiliki www-data), fallback ke sys_get_temp_dir().
     *
     * @return string Jalur lengkap berkas
     *
     * @throws \RuntimeException jika direktori tidak dapat dibuat
     */
    private function temporaryFilePath(string $extension): string
    {
        // Lokasi utama: storage/app/exports/activity-logs/
        $directory = storage_path('app/'.self::EXPORT_DIRECTORY);

        if (! is_dir($directory)) {
            $created = @mkdir($directory, 0755, true);
            if (! $created && ! is_dir($directory)) {
                // Fallback ke direktori sistem temp.
                $directory = sys_get_temp_dir().'/'.self::FALLBACK_DIRECTORY;
                if (! is_dir($directory)) {
                    @mkdir($directory, 0755, true);
                }
            } else {
                @chmod($directory, 0755);
            }
        } else {
            @chmod($directory, 0755);
        }

        return $directory.'/imnehnet-activity-logs-'
            .Carbon::now('Asia/Jakarta')->format('Ymd-His')
            .'-'.bin2hex(random_bytes(4)).'.'.$extension;
    }

    /**
     * Pastikan direktori induk dan berkas readable oleh web server (www-data).
     */
    private function ensureFileReadable(string $filePath): void
    {
        $dir = dirname($filePath);
        if (is_dir($dir)) {
            @chmod($dir, 0755);
        }

        if (file_exists($filePath)) {
            @chmod($filePath, 0644);
        }
    }
}
