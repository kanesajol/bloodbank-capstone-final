<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Donor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

trait InteractsWithDonorSearch
{
    /**
     * @return array{0: Collection<int, Donor>, 1: int}
     */
    protected function searchDonors(string $searchTerm): array
    {
        $query = Donor::query()->whereHas('donations');

        $words = $this->nameSearchWords($searchTerm);
        if ($words !== []) {
            foreach ($words as $word) {
                $like = '%'.addcslashes($word, '%_\\').'%';
                $query->where(function ($inner) use ($like) {
                    $inner->whereRaw('LOWER(full_name) like ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(last_name, \'\')) like ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(first_name, \'\')) like ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(middle_name, \'\')) like ?', [$like])
                        ->orWhereRaw('LOWER(barangay) like ?', [$like])
                        ->orWhereRaw('LOWER(municipality) like ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(province, \'\')) like ?', [$like])
                        ->orWhereRaw('LOWER(contact) like ?', [$like]);
                });
            }
            $this->rankNameMatches($query, $words);
        } else {
            $query->orderBy('full_name');
        }

        $total = (clone $query)->count();
        $donors = $query
            ->limit(100)
            ->get(['id', 'full_name', 'last_name', 'first_name', 'middle_name', 'suffix']);

        return [$donors, $total];
    }

    /**
     * @return array<int, string>
     */
    protected function nameSearchWords(string $term): array
    {
        $words = preg_split('/[\s,]+/u', mb_strtolower(trim($term))) ?: [];

        return array_values(array_filter($words, fn (string $word) => $word !== ''));
    }

    /**
     * @param  array<int, string>  $words
     */
    protected function rankNameMatches(Builder $query, array $words): void
    {
        if ($words === []) {
            $query->orderBy('full_name');

            return;
        }

        $parts = [];
        $bindings = [];
        foreach ($words as $word) {
            $prefix = addcslashes($word, '%_\\').'%';
            $parts[] = "CASE WHEN LOWER(COALESCE(last_name, '')) LIKE ? OR LOWER(COALESCE(first_name, '')) LIKE ? THEN 2 WHEN LOWER(COALESCE(middle_name, '')) LIKE ? THEN 1 ELSE 0 END";
            array_push($bindings, $prefix, $prefix, $prefix);
        }

        $query->orderByRaw('('.implode(' + ', $parts).') DESC', $bindings)->orderBy('full_name');
    }
}
