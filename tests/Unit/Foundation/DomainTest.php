<?php

namespace Tests\Unit\Foundation;

use App\Support\Domain;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DomainTest extends TestCase
{
    #[DataProvider('validUrls')]
    public function test_it_normalizes_domains(string $url, string $expected): void
    {
        $this->assertSame($expected, Domain::fromUrl($url));
    }

    public static function validUrls(): array
    {
        return [
            ['https://axiontic.com', 'axiontic.com'],
            ['WWW.Axiontic.com/', 'axiontic.com'],
            ['http://www.example.co.uk/services/', 'example.co.uk'],
            ['axiontic.ro', 'axiontic.ro'],
        ];
    }

    #[DataProvider('invalidUrls')]
    public function test_it_rejects_malformed_or_unsafe_urls(string $url): void
    {
        $this->assertNull(Domain::fromUrl($url));
    }

    public static function invalidUrls(): array
    {
        return [
            [''],
            ['not a url'],
            ['ftp://example.com'],
            ['javascript:alert(1)'],
            ['http://127.0.0.1'],
            ['http://localhost'],
            ['http://user:pass@example.com'],
            ['http://[::1]/'],
        ];
    }
}
