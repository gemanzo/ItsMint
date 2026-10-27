<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Never reach the real S3 bucket of card scans from tests
        Storage::fake('s3-card-scans');

        // Pages render without the compiled frontend assets (public/build),
        // which don't exist in CI or in a fresh checkout
        $this->withoutVite();
    }
}
