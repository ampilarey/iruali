<?php

namespace App\Support;

use App\Models\Product;

/**
 * A product's optional video: a YouTube (or Shorts), TikTok, Instagram (post or reel) or Facebook
 * (video or reel) link that a shop pastes in the product form, kept as provider + id
 * (products.video_provider, products.video_id). The product page shows a placeholder and creates
 * the provider's player only when the shopper presses play (products/_video.blade.php), so no
 * third-party request is made before that. SecurityHeaders allows exactly FRAME_SOURCES in
 * frame-src.
 */
final class ProductVideo
{
    /** provider => [name shown, aspect: landscape (16:9), portrait (9:16) or post (4:5)] */
    public const PROVIDERS = [
        'youtube' => ['YouTube', 'landscape'],
        'youtube_short' => ['YouTube', 'portrait'],
        'tiktok' => ['TikTok', 'portrait'],
        'instagram' => ['Instagram', 'post'],
        'instagram_reel' => ['Instagram', 'portrait'],
        'facebook' => ['Facebook', 'landscape'],
        'facebook_reel' => ['Facebook', 'portrait'],
    ];

    /** The only places the product page may load a player from (Content-Security-Policy frame-src). */
    public const FRAME_SOURCES = [
        'https://www.youtube-nocookie.com',
        'https://www.tiktok.com',
        'https://www.instagram.com',
        'https://www.facebook.com',
    ];

    /** Share links that only redirect to the video: the id is not in them. */
    protected const SHORT_LINK_HOSTS = ['vm.tiktok.com', 'vt.tiktok.com', 'fb.watch', 'youtube.app.goo.gl'];

    private function __construct(public readonly string $provider, public readonly string $id) {}

    /**
     * A stored provider + id, or null when either is missing or not one this class knows.
     */
    public static function make(?string $provider, ?string $id): ?self
    {
        if ($provider === null || $id === null || ! isset(self::PROVIDERS[$provider]) || ! self::validId($provider, $id)) {
            return null;
        }

        return new self($provider, $id);
    }

    public static function fromProduct(Product $product): ?self
    {
        return self::make($product->video_provider, $product->video_id);
    }

    /**
     * Read a pasted link: null when it is not a video on one of the supported sites.
     */
    public static function parse(?string $url): ?self
    {
        $parts = self::urlParts($url);
        if ($parts === null) {
            return null;
        }
        [$host, $segments, $query] = $parts;
        $v = is_string($query['v'] ?? null) ? $query['v'] : null;

        $found = match ($host) {
            'youtube.com', 'youtube-nocookie.com' => match ($segments[0] ?? '') {
                'watch' => ['youtube', $v],
                'shorts' => ['youtube_short', $segments[1] ?? null],
                'embed', 'live', 'v' => ['youtube', $segments[1] ?? null],
                default => null,
            },
            'youtu.be' => ['youtube', $segments[0] ?? null],
            'tiktok.com' => self::tiktok($segments),
            'instagram.com' => self::instagram($segments),
            'facebook.com' => self::facebook($segments, $v),
            default => null,
        };

        return $found ? self::make($found[0], is_string($found[1]) ? $found[1] : null) : null;
    }

    /**
     * Is this a share link that hides the video's id (vm.tiktok.com, fb.watch, facebook.com/share/…)?
     * The form asks for the full link instead.
     */
    public static function isShortLink(?string $url): bool
    {
        $parts = self::urlParts($url);
        if ($parts === null) {
            return false;
        }
        [$host, $segments] = $parts;

        return in_array($host, self::SHORT_LINK_HOSTS, true)
            || ($host === 'facebook.com' && ($segments[0] ?? '') === 'share');
    }

    /** "YouTube", "TikTok", "Instagram" or "Facebook". */
    public function providerName(): string
    {
        return self::PROVIDERS[$this->provider][0];
    }

    /** landscape (16:9), portrait (9:16: TikTok, Shorts and reels) or post (4:5: an Instagram post). */
    public function aspect(): string
    {
        return self::PROVIDERS[$this->provider][1];
    }

    public function isPortrait(): bool
    {
        return $this->aspect() === 'portrait';
    }

    /**
     * The player, started as soon as it loads (it is only loaded after a press on play).
     */
    public function embedUrl(): string
    {
        $id = rawurlencode($this->id);

        return match ($this->provider) {
            'youtube', 'youtube_short' => "https://www.youtube-nocookie.com/embed/{$id}?autoplay=1&rel=0&playsinline=1",
            'tiktok' => "https://www.tiktok.com/player/v1/{$id}?autoplay=1&rel=0",
            'instagram' => "https://www.instagram.com/p/{$id}/embed/",
            'instagram_reel' => "https://www.instagram.com/reel/{$id}/embed/",
            default => 'https://www.facebook.com/plugins/video.php?href='.rawurlencode($this->watchUrl()).'&show_text=false&autoplay=true', // facebook, facebook_reel
        };
    }

    /**
     * The video on the provider's own site (the "watch on" link, and what the form shows).
     */
    public function watchUrl(): string
    {
        $id = rawurlencode($this->id);

        return match ($this->provider) {
            'youtube' => "https://www.youtube.com/watch?v={$id}",
            'youtube_short' => "https://www.youtube.com/shorts/{$id}",
            'tiktok' => "https://www.tiktok.com/embed/v2/{$id}",
            'instagram' => "https://www.instagram.com/p/{$id}/",
            'instagram_reel' => "https://www.instagram.com/reel/{$id}/",
            'facebook' => "https://www.facebook.com/watch/?v={$id}",
            default => "https://www.facebook.com/reel/{$id}", // facebook_reel
        };
    }

    /**
     * Host (lower case, without www. or m.), path segments and query of a link, or null when it is
     * not an http(s) link. A link pasted without https:// is read as https.
     *
     * @return array{0: string, 1: list<string>, 2: array<array-key, mixed>}|null
     */
    protected static function urlParts(?string $url): ?array
    {
        $url = trim((string) $url);
        if ($url === '' || mb_strlen($url) > 500 || preg_match('/\s/', $url)) {
            return null;
        }
        if (! preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
            $url = 'https://'.$url;
        }

        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['host']) || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            return null;
        }

        $host = (string) preg_replace('/^(www\.|m\.|mobile\.)/', '', strtolower($parts['host']));
        $segments = array_values(array_filter(explode('/', (string) ($parts['path'] ?? '')), fn (string $s) => $s !== ''));
        parse_str((string) ($parts['query'] ?? ''), $query);

        return [$host, $segments, $query];
    }

    /**
     * tiktok.com/@shop/video/{id}, /embed/v2/{id}, /embed/{id} or /player/v1/{id}.
     *
     * @param  list<string>  $segments
     * @return array{0: string, 1: string|null}|null
     */
    protected static function tiktok(array $segments): ?array
    {
        if (str_starts_with($segments[0] ?? '', '@') && ($segments[1] ?? '') === 'video') {
            return ['tiktok', $segments[2] ?? null];
        }

        return match ($segments[0] ?? '') {
            'embed' => ['tiktok', ($segments[1] ?? '') === 'v2' ? ($segments[2] ?? null) : ($segments[1] ?? null)],
            'player' => ['tiktok', $segments[2] ?? null],
            default => null,
        };
    }

    /**
     * instagram.com/p/{code}, /reel/{code}, /reels/{code} or /tv/{code}, also after a user name.
     *
     * @param  list<string>  $segments
     * @return array{0: string, 1: string|null}|null
     */
    protected static function instagram(array $segments): ?array
    {
        if (count($segments) >= 3 && in_array($segments[1], ['p', 'reel', 'reels', 'tv'], true)) {
            array_shift($segments); // instagram.com/{user}/reel/{code}
        }

        return match ($segments[0] ?? '') {
            'p', 'tv' => ['instagram', $segments[1] ?? null],
            'reel', 'reels' => ['instagram_reel', $segments[1] ?? null],
            default => null,
        };
    }

    /**
     * facebook.com/{page}/videos/{id} (with or without a title before the id), /watch/?v={id},
     * /video.php?v={id} or /reel/{id}.
     *
     * @param  list<string>  $segments
     * @return array{0: string, 1: string|null}|null
     */
    protected static function facebook(array $segments, ?string $v): ?array
    {
        if (in_array($segments[0] ?? '', ['watch', 'video.php'], true)) {
            return ['facebook', $v];
        }
        if (($segments[0] ?? '') === 'reel') {
            return ['facebook_reel', $segments[1] ?? null];
        }

        $videos = array_search('videos', $segments, true);
        if ($videos === false) {
            return null;
        }
        $numeric = array_values(array_filter(array_slice($segments, $videos + 1), fn (string $s) => ctype_digit($s)));

        return ['facebook', $numeric[0] ?? null];
    }

    protected static function validId(string $provider, string $id): bool
    {
        $pattern = match ($provider) {
            'youtube', 'youtube_short' => '/^[A-Za-z0-9_-]{11}$/',
            'instagram', 'instagram_reel' => '/^[A-Za-z0-9_-]{5,40}$/',
            default => '/^\d{5,25}$/', // TikTok and Facebook ids are numbers
        };

        return (bool) preg_match($pattern, $id);
    }
}
