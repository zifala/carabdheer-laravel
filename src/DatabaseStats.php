<?php

declare(strict_types=1);

namespace Zifala\Carabdheer;

use Illuminate\Support\Facades\DB;
use Throwable;

class DatabaseStats
{
    /** @return array<string, mixed>|null */
    public function read(?string $connection = null): ?array
    {
        try {
            $db = DB::connection($connection);
            $driver = $db->getDriverName();
            $name = (string) $db->getDatabaseName();

            [$size, $connections, $max] = match ($driver) {
                'pgsql' => [
                    (int) $db->selectOne('select pg_database_size(current_database()) as v')->v,
                    (int) $db->selectOne('select count(*) as v from pg_stat_activity where datname = current_database()')->v,
                    (int) $db->selectOne('show max_connections')->max_connections,
                ],
                'mysql', 'mariadb' => [
                    (int) $db->selectOne('select coalesce(sum(data_length + index_length), 0) as v from information_schema.tables where table_schema = ?', [$name])->v,
                    (int) ($db->selectOne("show status like 'Threads_connected'")->Value ?? 0),
                    (int) ($db->selectOne("show variables like 'max_connections'")->Value ?? 0),
                ],
                'sqlite' => [is_file($name) ? (int) filesize($name) : 0, 1, null],
                default => [0, 0, null],
            };

            return array_filter([
                'type' => 'database_stats',
                'name' => basename($name),
                'engine' => $driver,
                'connection' => $db->getName(),
                'size_bytes' => $size,
                'connections' => $connections,
                'max_connections' => $max ?: null,
            ], fn (mixed $value) => $value !== null);
        } catch (Throwable) {
            return null;
        }
    }
}
