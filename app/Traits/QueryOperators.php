<?php

namespace App\Traits;

use Illuminate\Contracts\Database\Query\Expression;

trait QueryOperators
{
    /**
     * Get query operator mapping
     *
     * @param string $operator
     * @return string
     */
    protected function getQueryOperator(string $operator): string
    {
        return match (strtolower($operator)) {
            'eq' => '=',
            'gt' => '>',
            'gte' => '>=',
            'lt' => '<',
            'lte' => '<=',
            'like' => 'like',
            'notlike' => 'not like',
            'null' => 'null',
            'notnull' => 'notnull',
            default => 'like'
        };
    }

    /**
     * Get formatted query value
     *
     * @param string $operator
     * @param mixed $value
     * @return mixed
     */
    protected function formatQueryValue(string $operator, mixed $value): mixed
    {
        return match (strtolower($operator)) {
            'like', 'notlike' => "%{$value}%",
            'null', 'notnull' => null,
            default => $value
        };
    }

    /**
     * Apply query conditions
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $field
     * @param string $operator
     * @param mixed $value
     * @return void
     */
    protected function applyQueryCondition($query, array|Expression|string $field, string $operator, mixed $value): void
    {
        $queryOperator = $this->getQueryOperator($operator);
        
        if ($queryOperator === 'null') {
            $query->whereNull($field);
        } elseif ($queryOperator === 'notnull') {
            $query->whereNotNull($field);
        } else {
            $query->where($field, $queryOperator, $this->formatQueryValue($operator, $value));
        }
    }
} 