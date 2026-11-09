<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\BlogPost;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Names in blog texts are written as [[Jan de Vries]]. Logged in visitors see the name, everyone else only
 * the initials (J.d.V.). This happens on the server, so the full name never reaches a page, feed or search
 * result for someone who isn't logged in.
 */
class NameMasker
{
    /** [[…]] with anything but brackets in between (Trix may put tags like <strong> inside) */
    public const PATTERN = '/\[\[([^\[\]]+)\]\]/u';

    public function __construct(private Security $security) {}

    /** Whether the current visitor may see full names: logged in, and not halfway through two-factor login */
    public function canSeeNames(): bool
    {
        return $this->security->getUser() !== null && !$this->security->getToken() instanceof TwoFactorTokenInterface;
    }

    /** The text for the current visitor; $reveal forces a choice (false for public feeds and share previews) */
    public function forViewer(?string $text, ?bool $reveal = null): string
    {
        return ($reveal ?? $this->canSeeNames()) ? self::reveal($text) : self::mask($text);
    }

    /**
     * Search results for the current visitor: the database also matched inside [[names]], so for visitors who
     * can't see names a post with names only stays when the term is in its title or its text with initials.
     *
     * @param iterable<BlogPost> $posts
     * @return list<BlogPost>
     */
    public function filterSearchResults(iterable $posts, string $term): array
    {
        $posts = is_array($posts) ? array_values($posts) : iterator_to_array($posts, false);
        if ($this->canSeeNames()) {
            return $posts;
        }

        $term = trim($term);

        return array_values(array_filter($posts, function (BlogPost $post) use ($term): bool {
            if (!self::hasNames($post->getIntroText()) && !self::hasNames($post->getContent())) {
                return true;
            }
            foreach ([$post->getTitle(), self::mask($post->getIntroText()), self::mask($post->getContent())] as $text) {
                if (mb_stripos((string) $text, $term) !== false) {
                    return true;
                }
            }

            return false;
        }));
    }

    public static function hasNames(?string $text): bool
    {
        return $text !== null && preg_match(self::PATTERN, $text) === 1;
    }

    /** Every [[name]] replaced by its initials */
    public static function mask(?string $text): string
    {
        return (string) preg_replace_callback(self::PATTERN, fn (array $m) => htmlspecialchars(self::initials($m[1]), ENT_QUOTES), (string) $text);
    }

    /** Every [[name]] replaced by the name itself */
    public static function reveal(?string $text): string
    {
        return (string) preg_replace(self::PATTERN, '$1', (string) $text);
    }

    /** "Jan de Vries" → "J.d.V.", "Anne-Marie" → "A.M." */
    public static function initials(string $name): string
    {
        $plain = trim(html_entity_decode(strip_tags($name), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $initials = '';
        foreach (preg_split('/[\s\-\x{00A0}]+/u', $plain, -1, PREG_SPLIT_NO_EMPTY) as $word) {
            // The first letter, skipping a leading apostrophe or bracket
            if (preg_match('/\p{L}|\p{N}/u', $word, $letter)) {
                $initials .= $letter[0] . '.';
            }
        }

        return $initials;
    }
}
