<?php

namespace Tests\Unit;

use Tests\TestCase;

class AppTimezoneTest extends TestCase
{
    public function test_application_uses_sao_paulo_timezone(): void
    {
        $this->assertSame('America/Sao_Paulo', config('app.timezone'));
        $this->assertSame('America/Sao_Paulo', now()->timezoneName);
    }
}
