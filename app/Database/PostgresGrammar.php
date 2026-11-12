<?php

namespace App\Database;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\PostgresGrammar as BasePostgresGrammar;

/**
 * Postgres grammar that keeps LIKE case-insensitive.
 *
 * The upstream grammar compiles LIKE as `"column"::text like ?` so that LIKE
 * also works on non-text columns. That cast throws away `citext`, which is how
 * this schema reproduces MySQL's case-insensitive string comparison, so every
 * `where(..., 'like', ...)` would silently become case-sensitive and return
 * fewer rows with no error.
 *
 * Casting to `text` first keeps the upstream "any column type" behaviour, then
 * back to `citext` restores the case-insensitive match. MySQL is untouched:
 * this grammar is only ever used by the pgsql connection.
 */
class PostgresGrammar extends BasePostgresGrammar
{
    /**
     * Compile a basic where clause.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $where
     * @return string
     */
    protected function whereBasic(Builder $query, $where)
    {
        if (str_contains(strtolower($where['operator']), 'like')) {
            return sprintf(
                '%s::text::citext %s %s',
                $this->wrap($where['column']),
                $where['operator'],
                $this->parameter($where['value'])
            );
        }

        return parent::whereBasic($query, $where);
    }
}
