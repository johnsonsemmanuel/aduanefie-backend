<?php

namespace App\Database;

use Illuminate\Database\PostgresConnection as BasePostgresConnection;

/**
 * Postgres connection that installs App\Database\PostgresGrammar.
 *
 * Registered with Connection::resolverFor('pgsql', ...) in AppServiceProvider,
 * so every pgsql connection (default and any extra ones) picks it up lazily.
 */
class PostgresConnection extends BasePostgresConnection
{
    /**
     * Get the default query grammar instance.
     *
     * @return \Illuminate\Database\Query\Grammars\PostgresGrammar
     */
    protected function getDefaultQueryGrammar()
    {
        /** @var \App\Database\PostgresGrammar $grammar */
        ($grammar = new PostgresGrammar())->setConnection($this);

        return $this->withTablePrefix($grammar);
    }
}
