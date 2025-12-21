<?php

namespace App\Http\Traits;

use Illuminate\Support\Facades\Log;

trait Searchable
{
    /**
     * Search Within a Model and relationships
     */
    public function search(string $searchQuery, array $relationships)
    {

        $columns = $this->getConnection()->getSchemaBuilder()->getColumnListing($this->getTable());

        $names = collect($relationships)->pluck('name')->toArray();

        $results = $this->with($names)->where(function ($query) use ($columns, $searchQuery) {
            foreach ($columns as $column) {
                $query->orWhere($column, 'LIKE', '%'.$searchQuery.'%');
            }
        });

        $results->orWhere(function ($query) use ($relationships, $searchQuery) {
            foreach ($relationships as $key => $relationship) {
                if ($key == 0) {
                    $query->whereHas($relationship['name'], function ($subQuery) use ($searchQuery, $relationship) {
                        foreach ($relationship['columns'] as $key => $column) {
                            Log::alert(gettype($column));
                            if ($key == 0 && gettype($column) == 'string') {
                                $subQuery->where($column, 'LIKE', '%'.$searchQuery.'%');
                            } elseif (gettype($column) == 'string') {
                                $subQuery->orWhere($column, 'LIKE', '%'.$searchQuery.'%');
                            } else {
                                $subQuery->orWhereHas($key, function ($subQuery) use ($searchQuery, $column) {
                                    foreach ($column as $key => $subColumn) {
                                        if ($key == 0) {
                                            $subQuery->where($subColumn, 'LIKE', '%'.$searchQuery.'%');
                                        } else {
                                            $subQuery->orWhere($subColumn, 'LIKE', '%'.$searchQuery.'%');
                                        }
                                    }
                                });
                            }

                        }
                    });
                } else {

                    $query->orWhereHas($relationship['name'], function ($subQuery) use ($searchQuery, $relationship) {
                        foreach ($relationship['columns'] as $key => $column) {
                            Log::alert(gettype($column));
                            if ($key == 0 && gettype($column) == 'string') {
                                $subQuery->where($column, 'LIKE', '%'.$searchQuery.'%');
                            } elseif (gettype($column) == 'string') {
                                $subQuery->orWhere($column, 'LIKE', '%'.$searchQuery.'%');
                            } else {
                                $subQuery->orWhereHas($key, function ($subQuery) use ($searchQuery, $column) {
                                    foreach ($column as $key => $subColumn) {
                                        if ($key == 0) {
                                            $subQuery->where($subColumn, 'LIKE', '%'.$searchQuery.'%');
                                        } else {
                                            $subQuery->orWhere($subColumn, 'LIKE', '%'.$searchQuery.'%');
                                        }
                                    }
                                });
                            }

                        }
                    });
                }

            }
        });

        return $results = $results->latest()->paginate();
    }
}
