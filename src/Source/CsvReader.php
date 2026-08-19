<?php

namespace GlpiPlugin\Ticketmigration\Source;

final class CsvReader implements SourceReaderInterface
{
    /** @var list<SourceColumn>|null */
    private ?array $columns = null;

    public function __construct(private readonly string $path, private readonly CsvConfiguration $configuration = new CsvConfiguration())
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new \RuntimeException('CSV source is not readable.');
        }
    }

    public function rows(): iterable
    {
        $file = new \SplFileObject($this->path, 'rb');
        $file->setCsvControl($this->configuration->delimiter, $this->configuration->enclosure, $this->configuration->escape);
        $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::DROP_NEW_LINE | \SplFileObject::SKIP_EMPTY);
        $logicalRow = 0;
        foreach ($file as $values) {
            if ($values === [null] || $values === false) { continue; }
            $logicalRow++;
            $values = array_map(fn ($value) => $this->normalize((string) $value), $values);
            if ($logicalRow === 1 && $this->configuration->hasHeader) { continue; }
            yield new SourceRow($logicalRow, $values);
        }
    }

    public function columns(): array
    {
        if ($this->columns !== null) { return $this->columns; }
        $file = new \SplFileObject($this->path, 'rb');
        $file->setCsvControl($this->configuration->delimiter, $this->configuration->enclosure, $this->configuration->escape);
        $first = $file->fgetcsv();
        if ($first === false) { return $this->columns = []; }
        return $this->columns = array_map(
            fn ($value, $index) => new SourceColumn($index, $this->configuration->hasHeader ? $this->normalize((string) $value) : 'Column ' . ($index + 1)),
            $first,
            array_keys($first),
        );
    }

    public function preview(int $limit = 10): array
    {
        $rows = [];
        foreach ($this->rows() as $row) {
            $rows[] = $row;
            if (count($rows) >= $limit) { break; }
        }
        return $rows;
    }

    private function normalize(string $value): string
    {
        $value = str_starts_with($value, "\xEF\xBB\xBF") ? substr($value, 3) : $value;
        if (strcasecmp($this->configuration->encoding, 'UTF-8') !== 0) {
            $converted = iconv($this->configuration->encoding, 'UTF-8//TRANSLIT', $value);
            if ($converted === false) { throw new \RuntimeException('Unable to convert CSV encoding.'); }
            return $converted;
        }
        return $value;
    }
}
