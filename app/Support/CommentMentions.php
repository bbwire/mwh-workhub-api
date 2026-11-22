<?php

namespace App\Support;

class CommentMentions
{
    /**
     * Stored mention format: @[Display Name](user:123)
     *
     * @return list<int>
     */
    public static function idsFromBody(string $body): array
    {
        preg_match_all('/@\[[^\]]+\]\(user:(\d+)\)/', $body, $matches);

        return collect($matches[1] ?? [])
            ->map(fn (string $id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public static function token(int $userId, string $name): string
    {
        $safeName = str_replace(['[', ']'], '', $name);

        return "@[{$safeName}](user:{$userId})";
    }

    public static function excerpt(string $body, int $limit = 140): string
    {
        $plain = preg_replace('/@\[([^\]]+)\]\(user:\d+\)/', '@$1', $body) ?? $body;
        $plain = trim(preg_replace('/\s+/', ' ', $plain) ?? $plain);

        if (mb_strlen($plain) <= $limit) {
            return $plain;
        }

        return rtrim(mb_substr($plain, 0, $limit - 1)).'…';
    }
}
