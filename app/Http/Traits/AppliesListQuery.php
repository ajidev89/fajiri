<?php

namespace App\Http\Traits;

use Illuminate\Database\Eloquent\Builder;

trait AppliesListQuery
{
    /**
     * Apply search + sort from the incoming admin/list request.
     *
     * @param  array<int, string>  $searchColumns
     * @param  array<int, string>  $sortable
     * @param  array<int, array{name: string, columns?: array<int, string>}>  $searchRelations
     */
    protected function applyListQuery(
        Builder $query,
        $request,
        array $searchColumns = [],
        array $sortable = ['created_at', 'updated_at'],
        array $searchRelations = [],
        string $defaultSort = 'created_at',
        string $defaultOrder = 'desc',
    ): Builder {
        if ($request) {
            $search = $request->input('search') ?? $request->input('q');

            if (filled($search) && ($searchColumns || $searchRelations)) {
                $term = trim((string) $search);
                $query->where(function (Builder $q) use ($searchColumns, $searchRelations, $term) {
                    foreach ($searchColumns as $index => $column) {
                        $method = $index === 0 ? 'where' : 'orWhere';
                        $q->{$method}($column, 'like', "%{$term}%");
                    }

                    foreach ($searchRelations as $relation) {
                        $q->orWhereHas($relation['name'], function (Builder $sub) use ($relation, $term) {
                            foreach (($relation['columns'] ?? ['name']) as $index => $column) {
                                $method = $index === 0 ? 'where' : 'orWhere';
                                $sub->{$method}($column, 'like', "%{$term}%");
                            }
                        });
                    }
                });
            }

            $sortBy = $request->input('sort_by', $defaultSort);
            $sortOrder = strtolower((string) $request->input('sort_order', $defaultOrder));
        } else {
            $sortBy = $defaultSort;
            $sortOrder = $defaultOrder;
        }

        if (! in_array($sortOrder, ['asc', 'desc'], true)) {
            $sortOrder = $defaultOrder;
        }

        if (! in_array($sortBy, $sortable, true)) {
            $sortBy = $defaultSort;
        }

        return $query->orderBy($sortBy, $sortOrder);
    }
}
