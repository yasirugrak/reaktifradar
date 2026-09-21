<?php

namespace Tests\Unit;

use App\Services\ImportFailure;
use Illuminate\Database\QueryException;
use PDOException;
use PHPUnit\Framework\TestCase;

class ImportFailureTest extends TestCase
{
    public function test_diagnosis_identifies_source_auth_failure_without_disclosing_secrets(): void
    {
        $driver = new PDOException('password authentication failed for user "private-user" secret-value');
        $driver->errorInfo = ['08006', 7, $driver->getMessage()];
        $exception = new QueryException('legacy', 'select ? from sensitive', ['secret-binding'], $driver);
        $diagnosis = ImportFailure::describe($exception);
        $this->assertSame('08006', $diagnosis['state']);
        $this->assertStringContainsString('Kaynak', $diagnosis['connection']);
        $this->assertStringContainsString('parolası kabul edilmedi', $diagnosis['reason']);
        $this->assertStringNotContainsString('private-user', json_encode($diagnosis));
        $this->assertStringNotContainsString('secret-', json_encode($diagnosis));
    }

    public function test_missing_target_table_has_actionable_safe_diagnosis(): void
    {
        $driver = new PDOException('relation "sensitive-table" does not exist');
        $driver->errorInfo = ['42P01', 7, $driver->getMessage()];
        $diagnosis = ImportFailure::describe(new QueryException('pgsql', 'select * from sensitive', [], $driver));
        $this->assertStringContainsString('Hedef', $diagnosis['connection']);
        $this->assertStringContainsString('migration', $diagnosis['reason']);
        $this->assertStringNotContainsString('sensitive', json_encode($diagnosis));
    }
}
