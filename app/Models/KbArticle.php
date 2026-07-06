<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class KbArticle extends Model
{
    protected $guarded = [];

    /**
     * Tier 1 of the chatbot escalation ladder: instant, free, keyword-matched answers -
     * no external API call. Case-insensitive substring match of the message against each
     * article's comma-separated keyword list; first match wins.
     */
    public static function findMatch(string $message): ?self
    {
        $needle = Str::lower($message);

        return static::query()
            ->get()
            ->first(function (self $article) use ($needle) {
                foreach (explode(',', $article->keywords) as $keyword) {
                    $keyword = Str::lower(trim($keyword));

                    if ($keyword !== '' && str_contains($needle, $keyword)) {
                        return true;
                    }
                }

                return false;
            });
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($term) {
            $query->where('question', 'like', "%{$term}%")
                ->orWhere('keywords', 'like', "%{$term}%");
        });
    }
}
