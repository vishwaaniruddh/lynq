<?php
/**
 * Simple XLSX Parser
 * Standard native PHP parser for .xlsx files using ZipArchive and SimpleXML
 */
class SimpleXLSX {
    private array $rows = [];
    private bool $parsed = false;
    private string $error = '';

    public static function parse(string $filename): ?self {
        $xlsx = new self();
        if ($xlsx->parseFile($filename)) {
            return $xlsx;
        }
        return null;
    }

    public function parseFile(string $filename): bool {
        if (!file_exists($filename)) {
            $this->error = "File not found: {$filename}";
            return false;
        }

        if (!class_exists('ZipArchive')) {
            $this->error = 'PHP ZipArchive extension is not enabled.';
            return false;
        }

        $zip = new ZipArchive();
        if ($zip->open($filename) !== true) {
            $this->error = 'Could not open file as ZipArchive.';
            return false;
        }

        // 1. Load shared strings table
        $sharedStrings = [];
        $ssData = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssData !== false) {
            $xml = @simplexml_load_string($ssData);
            if ($xml && isset($xml->si)) {
                foreach ($xml->si as $val) {
                    if (isset($val->t)) {
                        $sharedStrings[] = (string)$val->t;
                    } elseif (isset($val->r)) {
                        $text = '';
                        foreach ($val->r as $run) {
                            $text .= (string)$run->t;
                        }
                        $sharedStrings[] = $text;
                    } else {
                        $sharedStrings[] = '';
                    }
                }
            }
        }

        // 2. Locate worksheet xml
        $sheetData = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetData === false) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if (preg_match('/xl\/worksheets\/sheet\d+\.xml/i', $stat['name'])) {
                    $sheetData = $zip->getFromName($stat['name']);
                    break;
                }
            }
        }

        $zip->close();

        if ($sheetData === false) {
            $this->error = 'No worksheet found in XLSX archive.';
            return false;
        }

        $xml = @simplexml_load_string($sheetData);
        if (!$xml || !isset($xml->sheetData->row)) {
            $this->rows = [];
            $this->parsed = true;
            return true;
        }

        $rows = [];
        foreach ($xml->sheetData->row as $rowNode) {
            $rowCells = [];
            foreach ($rowNode->c as $cell) {
                $cellRef = (string)$cell['r'];
                $colLetter = preg_replace('/[0-9]/', '', $cellRef);
                $colIdx = $this->columnLetterToIndex($colLetter);

                $cellType = (string)$cell['t'];
                $val = isset($cell->v) ? (string)$cell->v : '';

                if ($cellType === 's' && isset($sharedStrings[(int)$val])) {
                    $val = $sharedStrings[(int)$val];
                } elseif ($cellType === 'b') {
                    $val = $val === '1' ? 'true' : 'false';
                }

                $rowCells[$colIdx] = trim($val);
            }
            ksort($rowCells);

            if (!empty($rowCells)) {
                $maxCol = max(array_keys($rowCells));
                $fullRow = [];
                for ($c = 0; $c <= $maxCol; $c++) {
                    $fullRow[$c] = $rowCells[$c] ?? '';
                }
                $rows[] = $fullRow;
            }
        }

        $this->rows = $rows;
        $this->parsed = true;
        return true;
    }

    public function rows(): array {
        return $this->rows;
    }

    public function getError(): string {
        return $this->error;
    }

    private function columnLetterToIndex(string $column): int {
        $column = strtoupper($column);
        $length = strlen($column);
        $index = 0;
        for ($i = 0; $i < $length; $i++) {
            $index = $index * 26 + (ord($column[$i]) - ord('A') + 1);
        }
        return $index - 1;
    }
}
