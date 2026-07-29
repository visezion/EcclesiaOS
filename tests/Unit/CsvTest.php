<?php

namespace Tests\Unit;

use App\Support\Csv;
use PHPUnit\Framework\TestCase;

final class CsvTest extends TestCase
{
    public function test_spreadsheet_formulas_are_neutralized(): void
    {
        $this->assertSame("'=HYPERLINK(\"https://example.test\")", Csv::sanitize('=HYPERLINK("https://example.test")'));
        $this->assertSame("'\t@SUM(A1:A2)", Csv::sanitize("\t@SUM(A1:A2)"));
        $this->assertSame("'-10+20", Csv::sanitize('-10+20'));
        $this->assertSame('Normal ministry', Csv::sanitize('Normal ministry'));
        $this->assertSame(42, Csv::sanitize(42));
    }

    public function test_rows_are_encoded_as_valid_sanitized_csv(): void
    {
        $this->assertSame("\"O'Brien, John\",'=1+1\n", Csv::row(["O'Brien, John", '=1+1']));
    }
}
