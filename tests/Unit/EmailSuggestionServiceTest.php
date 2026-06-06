<?php

namespace Tests\Unit;

use App\Services\EmailSuggestionService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EmailSuggestionServiceTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function typoProvider(): array
    {
        return [
            'second-level typo' => ['john@gmial.com', 'john@gmail.com'],
            'doubled letter' => ['jane@yahooo.com', 'jane@yahoo.com'],
            'tld typo (con)' => ['kim@gmail.con', 'kim@gmail.com'],
            'compound typo (both parts)' => ['amy@gmial.con', 'amy@gmail.com'],
            'hotmail misspelling' => ['bob@hotmial.com', 'bob@hotmail.com'],
            'outlook misspelling' => ['sue@outlok.com', 'sue@outlook.com'],
            'comcast misspelling' => ['ed@comcsat.net', 'ed@comcast.net'],
            'preserves local part with dots' => ['john.s.doe@gmial.com', 'john.s.doe@gmail.com'],
            // International / regional providers added in the targeted merge.
            'yandex typo' => ['ivan@yandx.com', 'ivan@yandex.com'],
            // Caught by the whole-domain pass (1 edit from "web.de"), NOT the
            // second-level split — "web" is below MIN_SECOND_LEVEL_LENGTH (5).
            'web.de tld typo' => ['hans@web.ed', 'hans@web.de'],
            'qq compound typo' => ['li@qq.con', 'li@qq.com'],
            'rogers (CA) typo' => ['amy@rogers.con', 'amy@rogers.com'],
        ];
    }

    #[DataProvider('typoProvider')]
    public function test_suggests_correction_for_typos(string $input, string $expected): void
    {
        $this->assertSame($expected, EmailSuggestionService::suggest($input));
    }

    /**
     * @return array<string, array{0: ?string}>
     */
    public static function noSuggestionProvider(): array
    {
        return [
            'correct gmail' => ['real@gmail.com'],
            'correct outlook' => ['user@outlook.com'],
            'correct co.uk' => ['user@yahoo.co.uk'],
            'unknown but plausible domain' => ['staff@mycompany.com'],
            'unknown org' => ['info@lightningclass.org'],
            // ccTLD safety: a valid two-letter country code must never be
            // "corrected" toward a one-edit-away neighbour (.cl->.co, .es->.eu).
            'spanish ccTLD .es' => ['socio@empresa.es'],
            'chilean ccTLD .cl' => ['vela@club.cl'],
            'mexican ccTLD .mx' => ['regata@club.mx'],
            'unlisted ccTLD .cn not corrected to .co' => ['user@site.cn'],
            'unlisted ccTLD .ec not corrected to .eu' => ['user@vela.ec'],
            'no domain part' => ['justtext'],
            'empty domain' => ['user@'],
            'empty local part' => ['@gmail.com'],
            'dotless domain' => ['user@localhost'],
            'null' => [null],
            'empty string' => [''],
        ];
    }

    #[DataProvider('noSuggestionProvider')]
    public function test_returns_null_when_no_safe_suggestion(?string $input): void
    {
        $this->assertNull(EmailSuggestionService::suggest($input));
    }

    public function test_does_not_guess_at_short_second_level_labels(): void
    {
        // "we" is one edit from the listed "me", but short labels are never used
        // as correction targets, so we must not turn "we.com" into "me.com".
        $this->assertNull(EmailSuggestionService::suggest('user@we.com'));
    }

    public function test_normalizes_case_before_suggesting(): void
    {
        $this->assertSame('john@gmail.com', EmailSuggestionService::suggest('John@GMIAL.COM'));
    }
}
