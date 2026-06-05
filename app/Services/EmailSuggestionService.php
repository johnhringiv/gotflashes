<?php

namespace App\Services;

/**
 * Suggests a corrected email address when the domain looks like a typo of a
 * popular provider (e.g. "user@gmial.com" -> "user@gmail.com").
 *
 * A server-side port of the mailcheck.js approach using PHP's built-in
 * levenshtein(): no JS dependency, no network call. It runs inside the
 * Livewire blur round-trip that already fires for email validation, so the
 * suggestion is essentially free. Purely advisory friction-reduction — the
 * verification email remains the real liveness check.
 */
class EmailSuggestionService
{
    /**
     * Popular full domains, used for whole-domain close matching.
     *
     * @var list<string>
     */
    private const DOMAINS = [
        // Global webmail
        'gmail.com', 'googlemail.com', 'yahoo.com', 'ymail.com', 'rocketmail.com',
        'hotmail.com', 'outlook.com', 'live.com', 'msn.com', 'aol.com', 'aim.com',
        'icloud.com', 'me.com', 'mac.com', 'proton.me', 'protonmail.com',
        'gmx.com', 'mail.com', 'hey.com', 'zoho.com', 'yandex.com', 'qq.com',
        'web.de', 'titan.email',
        // US ISPs
        'comcast.net', 'verizon.net', 'att.net', 'sbcglobal.net', 'cox.net',
        'charter.net', 'earthlink.net', 'optonline.net', 'bellsouth.net',
        // Canada / Australia / New Zealand / UK regional providers
        'rogers.com', 'shaw.ca', 'telus.net', 'sympatico.ca', 'optusnet.com.au',
        'xtra.co.nz', 'sky.com', 'btinternet.com', 'yahoo.co.uk', 'hotmail.co.uk',
    ];

    /**
     * Second-level domains (the label before the TLD) we are willing to
     * suggest. Only entries of length >= MIN_SECOND_LEVEL_LENGTH are used as
     * correction targets, so short labels like "me"/"att" are never guessed at
     * (which would turn "we.com" into "me.com"); exact matches still pass
     * through untouched.
     *
     * @var list<string>
     */
    private const SECOND_LEVEL_DOMAINS = [
        'gmail', 'googlemail', 'yahoo', 'ymail', 'rocketmail', 'hotmail',
        'outlook', 'live', 'icloud', 'proton', 'protonmail', 'comcast',
        'verizon', 'sbcglobal', 'charter', 'earthlink', 'optonline', 'btinternet',
    ];

    /**
     * Top-level domains used for TLD close matching.
     *
     * Only entries of length >= MIN_TOP_LEVEL_LENGTH are used as correction
     * targets (see MIN_TOP_LEVEL_LENGTH). The two-letter country codes here are
     * therefore recognized as valid but are never *suggested* as a correction —
     * that is what keeps a valid ".cl"/".es"/".cn" from being "fixed" to a
     * one-edit-away neighbour like ".co"/".eu".
     *
     * @var list<string>
     */
    private const TOP_LEVEL_DOMAINS = [
        'com', 'net', 'org', 'edu', 'gov', 'mil', 'info', 'biz', 'io',
        'co.uk', 'com.au', 'co.nz', 'net.au',
        // Two-letter ccTLDs: recognized as valid, never correction targets.
        'co', 'us', 'ca', 'uk', 'de', 'fr', 'it', 'nl', 'eu', 'es', 'mx', 'ar', 'cl',
    ];

    private const DOMAIN_THRESHOLD = 2;

    private const SECOND_LEVEL_THRESHOLD = 2;

    private const TOP_LEVEL_THRESHOLD = 1;

    private const MIN_SECOND_LEVEL_LENGTH = 5;

    private const MIN_TOP_LEVEL_LENGTH = 3;

    /**
     * Suggest a corrected email, or null when the address is already fine or
     * too far from any known domain to guess safely.
     */
    public static function suggest(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $email = strtolower(trim($email));

        // Need exactly one usable @ with non-empty local and domain parts.
        $atPos = strrpos($email, '@');
        if ($atPos === false || $atPos === 0 || $atPos === strlen($email) - 1) {
            return null;
        }

        $address = substr($email, 0, $atPos);
        $domain = substr($email, $atPos + 1);

        // A domain needs a dot to split into second-level + TLD ("localhost" won't).
        if (! str_contains($domain, '.')) {
            return null;
        }

        // Already a known-good domain — nothing to suggest.
        if (in_array($domain, self::DOMAINS, true)) {
            return null;
        }

        // 1) Try to correct the whole domain against popular domains.
        $closestDomain = self::closest($domain, self::DOMAINS, self::DOMAIN_THRESHOLD);
        if ($closestDomain !== null && $closestDomain !== $domain) {
            return $address.'@'.$closestDomain;
        }

        // 2) Otherwise correct the second-level and top-level parts separately,
        //    which catches compound typos like "gmial.con".
        // Split on the FIRST dot: for compound TLDs like "co.uk" the full
        // remainder matches a TOP_LEVEL_DOMAINS entry directly. For deeper
        // hostnames the parts won't match any list and we fall through to no
        // suggestion — the intended safe behavior.
        $dotPos = strpos($domain, '.');
        $secondLevel = substr($domain, 0, $dotPos);
        $topLevel = substr($domain, $dotPos + 1);

        $closestSecondLevel = self::closest(
            $secondLevel,
            self::SECOND_LEVEL_DOMAINS,
            self::SECOND_LEVEL_THRESHOLD,
            self::MIN_SECOND_LEVEL_LENGTH
        );
        $closestTopLevel = self::closest($topLevel, self::TOP_LEVEL_DOMAINS, self::TOP_LEVEL_THRESHOLD, self::MIN_TOP_LEVEL_LENGTH);

        $suggestedDomain = ($closestSecondLevel ?? $secondLevel).'.'.($closestTopLevel ?? $topLevel);

        if ($suggestedDomain !== $domain) {
            return $address.'@'.$suggestedDomain;
        }

        return null;
    }

    /**
     * Return the closest candidate to $input within $threshold edits, or null.
     * An exact match returns null (nothing to correct). Candidates shorter than
     * $minCandidateLength are ignored as targets.
     *
     * Candidates must share $input's first character. Real-world typos almost
     * never alter the leading character, and this guard prevents short distinct
     * domains from colliding (e.g. "we.com" being "corrected" to "me.com").
     *
     * @param  list<string>  $candidates
     */
    private static function closest(string $input, array $candidates, int $threshold, int $minCandidateLength = 0): ?string
    {
        if ($input === '' || in_array($input, $candidates, true)) {
            return null;
        }

        $best = null;
        $bestDistance = PHP_INT_MAX;

        foreach ($candidates as $candidate) {
            // Byte-index first-char comparison is intentional: candidates (and
            // typical email domains) are ASCII. A multibyte input simply won't
            // match any ASCII candidate's first byte, yielding no suggestion.
            if (strlen($candidate) < $minCandidateLength || $candidate[0] !== $input[0]) {
                continue;
            }

            $distance = levenshtein($input, $candidate);
            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $best = $candidate;
            }
        }

        return $bestDistance <= $threshold ? $best : null;
    }
}
