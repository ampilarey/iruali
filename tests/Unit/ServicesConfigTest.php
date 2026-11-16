<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * config/services.php as PHP really loads it. A key written twice silently replaces the first
 * (that once wiped out the Facebook sign-in keys), and tests that set config() directly can't see it.
 */
class ServicesConfigTest extends TestCase
{
    protected function load(array $env): array
    {
        foreach ($env as $key => $value) {
            putenv("$key=$value");
            $_ENV[$key] = $_SERVER[$key] = $value;
        }
        try {
            return require dirname(__DIR__, 2).'/config/services.php';
        } finally {
            foreach (array_keys($env) as $key) {
                putenv($key);
                unset($_ENV[$key], $_SERVER[$key]);
            }
        }
    }

    public function test_facebook_sign_in_and_page_posting_keep_their_own_settings(): void
    {
        $config = $this->load([
            'FACEBOOK_CLIENT_ID' => 'app-id', 'FACEBOOK_CLIENT_SECRET' => 'app-secret', 'FACEBOOK_PAGE_ID' => 'page-1',
        ]);

        $this->assertSame('app-id', $config['facebook']['client_id']);
        $this->assertSame('app-secret', $config['facebook']['client_secret']);
        $this->assertTrue($config['facebook']['pkce']);
        $this->assertSame('page-1', $config['facebook_page']['page_id']);
    }

    public function test_no_top_level_key_is_written_twice(): void
    {
        $tokens = token_get_all(file_get_contents(dirname(__DIR__, 2).'/config/services.php'));
        $depth = 0;
        $keys = [];
        $previous = null;
        foreach ($tokens as $token) {
            if (is_string($token)) {
                $depth += match ($token) {
                    '[', '(' => 1, ']', ')' => -1, default => 0
                };
                if ($token !== ' ') {
                    $previous = $token;
                }

                continue;
            }
            if ($token[0] === T_WHITESPACE || $token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                continue;
            }
            // A top-level key: a string at depth 1 right after '[' or ','
            if ($token[0] === T_CONSTANT_ENCAPSED_STRING && $depth === 1 && in_array($previous, ['[', ','], true)) {
                $keys[] = trim($token[1], '\'"');
            }
            $previous = $token[1];
        }

        $this->assertNotEmpty($keys);
        $this->assertSame([], array_values(array_unique(array_diff_assoc($keys, array_unique($keys)))), 'Keys written twice in config/services.php');
    }
}
