<?php

namespace Tests\Feature\Localization;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class SetLocaleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api')->get('/_test/locale', function () {
            return response()->json(['locale' => app()->getLocale()]);
        });
    }

    public function test_accept_language_ar_eg_prefers_arabic(): void
    {
        $this->withHeader('Accept-Language', 'ar-EG,ar;q=0.9,en;q=0.5')
            ->getJson('/_test/locale')
            ->assertOk()
            ->assertJson(['locale' => 'ar']);
    }

    public function test_unsupported_first_language_falls_through_to_arabic(): void
    {
        $this->withHeader('Accept-Language', 'fr;q=1.0,ar;q=0.5')
            ->getJson('/_test/locale')
            ->assertOk()
            ->assertJson(['locale' => 'ar']);
    }

    public function test_unsupported_only_falls_back_to_english(): void
    {
        $this->withHeader('Accept-Language', 'fr')
            ->getJson('/_test/locale')
            ->assertOk()
            ->assertJson(['locale' => 'en']);
    }

    public function test_q_zero_is_rejected_and_falls_back_to_english(): void
    {
        $this->withHeader('Accept-Language', 'ar;q=0')
            ->getJson('/_test/locale')
            ->assertOk()
            ->assertJson(['locale' => 'en']);
    }

    public function test_en_us_normalizes_to_en(): void
    {
        $this->withHeader('Accept-Language', 'en-US')
            ->getJson('/_test/locale')
            ->assertOk()
            ->assertJson(['locale' => 'en']);
    }

    public function test_absent_header_uses_default(): void
    {
        $this->getJson('/_test/locale')
            ->assertOk()
            ->assertJson(['locale' => 'en']);
    }
}
