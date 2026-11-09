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
 *
 * When two different names in the same post would get the same initials, they get a second letter to tell
 * them apart: Karel and Kees become Ka. and Ke., Jan Jansen and Joop Jansen Ja.J. and Jo.J. Never more than
 * two letters per word: Karel and Karin can't be told apart that way, so both stay K. The post's intro, content and video titles together
 * decide this, so a name is shortened the same way everywhere in the post.
 */
class NameMasker
{
    /** [[…]] with anything but brackets in between (Trix may put tags like <strong> inside) */
    public const PATTERN = '/\[\[([^\[\]]+)\]\]/u';

    /** At most this many letters of a word, also when two names can't be told apart with them */
    public const MAX_LETTERS = 2;

    public function __construct(private Security $security) {}

    /** Whether the current visitor may see full names: logged in, and not halfway through two-factor login */
    public function canSeeNames(): bool
    {
        return $this->security->getUser() !== null && !$this->security->getToken() instanceof TwoFactorTokenInterface;
    }

    /**
     * The text for the current visitor. $post gives the names to keep apart (see the class comment);
     * $reveal forces a choice (false for public feeds and share previews).
     */
    public function forViewer(?string $text, ?BlogPost $post = null, ?bool $reveal = null): string
    {
        return ($reveal ?? $this->canSeeNames()) ? self::reveal($text) : self::mask($text, $post !== null ? self::postContext($post) : null);
    }

    /** All texts of a post that can hold names, so they are shortened the same way everywhere in it */
    public static function postContext(BlogPost $post): string
    {
        $texts = [$post->getIntroText(), $post->getContent()];
        foreach ($post->getVideos() as $video) {
            $texts[] = $video->getTitle();
        }

        return implode("\n", array_filter($texts, fn ($text) => $text !== null && $text !== ''));
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
            $context = self::postContext($post);
            foreach ([$post->getTitle(), self::mask($post->getIntroText(), $context), self::mask($post->getContent(), $context)] as $text) {
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

    /**
     * Every [[name]] replaced by its initials; $context is the text whose names must stay apart
     * (the whole post; without it only the names in $text itself)
     */
    public static function mask(?string $text, ?string $context = null): string
    {
        preg_match_all(self::PATTERN, ($context ?? '') . "\n" . $text, $matches);
        $short = self::abbreviations($matches[1]);

        return (string) preg_replace_callback(self::PATTERN, fn (array $m) => htmlspecialchars($short[self::key($m[1])] ?? self::initials($m[1]), ENT_QUOTES), (string) $text);
    }

    /** Every [[name]] replaced by the name itself */
    public static function reveal(?string $text): string
    {
        return (string) preg_replace(self::PATTERN, '$1', (string) $text);
    }

    /** "Jan de Vries" → "J.d.V.", "Anne-Marie" → "A.M." */
    public static function initials(string $name): string
    {
        $words = self::words($name);

        return self::shorten($words, array_fill(0, count($words), 1));
    }

    /**
     * Short forms of these names: initials, with a second letter where two names would get the same ones.
     * Word by word (first name first), a group of names with the same short form gets the letters it needs to
     * tell its words apart, at most MAX_LETTERS; names that still look the same there go on to the next word.
     * Karel/Kees → Ka./Ke., Jan Jansen/Joop Jansen → Ja.J./Jo.J., Jan Jansen/Jan Pieters → J.J./J.P. (no clash),
     * Karel/Karin → K./K. (two letters can't tell them apart, so they add nothing), Karel/Kees/Karin → Ka./Ke./Ka.
     *
     * @param list<string> $names
     * @return array<string, string> by self::key()
     */
    public static function abbreviations(array $names): array
    {
        $words = [];
        foreach ($names as $name) {
            $words[self::key($name)] ??= self::words($name);
        }
        $lengths = array_map(fn (array $w) => array_fill(0, count($w), 1), $words);

        $groups = [];
        foreach ($words as $key => $nameWords) {
            $groups[self::shorten($nameWords, $lengths[$key])][] = (string) $key;
        }
        foreach ($groups as $keys) {
            self::separate($keys, 0, $words, $lengths);
        }

        $result = [];
        foreach ($words as $key => $nameWords) {
            $result[(string) $key] = self::shorten($nameWords, $lengths[$key]);
        }

        return $result;
    }

    /**
     * Gives the names in $keys, which have the same short form so far, the letters at $position needed to tell
     * their different words there apart (at most MAX_LETTERS), then does the same at the next word for each set
     * of names that still look the same.
     *
     * @param list<string> $keys
     * @param array<string, list<string>> $words
     * @param array<string, array<int, int>> $lengths
     */
    private static function separate(array $keys, int $position, array $words, array &$lengths): void
    {
        if (count($keys) < 2 || $position >= max(array_map(fn (string $key) => count($words[$key]), $keys))) {
            return;
        }

        // The names by their word at this position ('' when the name has fewer words)
        $byWord = [];
        foreach ($keys as $key) {
            $byWord[mb_strtolower($words[$key][$position] ?? '')][] = $key;
        }

        // Letters needed: one more than the longest beginning that two different words share
        $different = array_keys($byWord);
        $needed = 1;
        foreach ($different as $i => $a) {
            foreach (array_slice($different, $i + 1) as $b) {
                $shared = 0;
                while ($shared < mb_strlen((string) $a) && mb_substr((string) $a, $shared, 1) === mb_substr((string) $b, $shared, 1)) {
                    ++$shared;
                }
                $needed = max($needed, $shared + 1);
            }
        }
        $needed = min($needed, self::MAX_LETTERS);
        $same = self::byStart($keys, $position, $words, $needed);
        // A second letter that tells no names apart would only give more away
        if (count($same) === 1) {
            $needed = 1;
        }
        foreach ($keys as $key) {
            if (isset($words[$key][$position])) {
                $lengths[$key][$position] = $needed;
            }
        }

        foreach ($same as $sameStart) {
            self::separate($sameStart, $position + 1, $words, $lengths);
        }
    }

    /**
     * The names grouped by how their word at $position starts
     *
     * @param list<string> $keys
     * @param array<string, list<string>> $words
     * @return array<string, list<string>>
     */
    private static function byStart(array $keys, int $position, array $words, int $letters): array
    {
        $groups = [];
        foreach ($keys as $key) {
            $groups[mb_strtolower(mb_substr($words[$key][$position] ?? '', 0, $letters))][] = $key;
        }

        return $groups;
    }

    /** The same name written slightly differently (tags, spaces, capitals) counts as one */
    private static function key(string $name): string
    {
        return mb_strtolower(implode(' ', self::words($name)));
    }

    /**
     * The words of a name as plain text, each starting at its first letter (so 't Hart → t, Hart)
     *
     * @return list<string>
     */
    private static function words(string $name): array
    {
        $plain = html_entity_decode(strip_tags($name), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $words = [];
        foreach (preg_split('/[\s\-\x{00A0}]+/u', $plain, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            if (preg_match('/[\p{L}\p{N}].*/u', $word, $m)) {
                $words[] = $m[0];
            }
        }

        return $words;
    }

    /**
     * @param list<string> $words
     * @param array<int, int> $lengths letters to keep per word
     */
    private static function shorten(array $words, array $lengths): string
    {
        $short = '';
        foreach ($words as $i => $word) {
            $short .= mb_substr($word, 0, $lengths[$i] ?? 1) . '.';
        }

        return $short;
    }
}
