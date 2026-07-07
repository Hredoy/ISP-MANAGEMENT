<?php

namespace Tests\Support;

use RouterOS\Client;
use RouterOS\Interfaces\QueryInterface;

/**
 * In-process stand-in for `RouterOS\Client` - no socket is ever opened. Simulates the generic
 * print/add/set/remove wire semantics RealMikroTikService relies on, keyed by RouterOS "table"
 * (e.g. `/ppp/secret`, `/queue/simple`), so the exact same PHPUnit test bodies used against
 * MockMikroTikService can also run against RealMikroTikService + this fake (see
 * MikroTikServiceContractTest) without touching real hardware.
 */
class FakeRouterOsClient extends Client
{
    /** @var array<string, list<array<string, string>>> */
    private array $tables = [];

    /** @var array<string, int> */
    private array $nextId = [];

    private ?QueryInterface $lastQuery = null;

    public function __construct()
    {
        // Deliberately skip the parent constructor - it opens a real socket connection.
    }

    /**
     * @param  array<string, mixed>  $rows
     */
    public function seed(string $table, array $rows): void
    {
        $this->tables[$table] = array_values($rows);
        $this->nextId[$table] = count($rows) + 1;
    }

    public function query($endpoint, ?array $where = null, ?string $operations = null, ?string $tag = null): \RouterOS\Interfaces\ClientInterface
    {
        $this->lastQuery = $endpoint;

        return $this;
    }

    public function read(bool $parse = true, array $options = []): array
    {
        $query = $this->lastQuery;
        $path = $query->getEndpoint();
        [$table, $action] = $this->splitEndpoint($path);
        $attrs = $this->parseAttributes($query->getAttributes());

        return match ($action) {
            'print' => $this->handlePrint($table, $attrs),
            'add' => $this->handleAdd($table, $attrs),
            'set' => $this->handleSet($table, $attrs),
            'remove' => $this->handleRemove($table, $attrs),
            'move' => $this->handleMove($table, $attrs),
            default => [],
        };
    }

    private function splitEndpoint(string $path): array
    {
        $segments = explode('/', trim($path, '/'));
        $action = array_pop($segments);

        return ['/'.implode('/', $segments), $action];
    }

    /**
     * @return array{equal: array<string, string>, where: array<string, string>}
     */
    private function parseAttributes(array $attributes): array
    {
        $equal = [];
        $where = [];

        foreach ($attributes as $token) {
            if (str_starts_with($token, '=')) {
                [$key, $value] = explode('=', substr($token, 1), 2) + [null, null];
                $equal[$key] = $value;
            } elseif (str_starts_with($token, '?')) {
                [$key, $value] = explode('=', substr($token, 1), 2) + [null, null];
                $where[$key] = $value;
            }
        }

        return ['equal' => $equal, 'where' => $where];
    }

    private function handlePrint(string $table, array $attrs): array
    {
        $rows = $this->tables[$table] ?? [];

        // This app's RealMikroTikService filters `/print` queries with both `->where()` (`?key=value`)
        // and `->equal()` (`=key=value`) tokens depending on the call site - both are treated as
        // filters here (bar `.proplist`, which only restricts returned columns on a real router).
        foreach ([...$attrs['where'], ...$attrs['equal']] as $key => $value) {
            if ($key === '.proplist') {
                continue;
            }

            $rows = array_values(array_filter($rows, fn (array $row) => ($row[$key] ?? null) === $value));
        }

        return $rows;
    }

    private function handleAdd(string $table, array $attrs): array
    {
        $id = $this->nextId[$table] ??= 1;
        $row = ['.id' => "*{$id}", ...$attrs['equal']];

        $this->tables[$table][] = $row;
        $this->nextId[$table] = $id + 1;

        return [$row];
    }

    private function handleSet(string $table, array $attrs): array
    {
        $id = $attrs['equal']['.id'] ?? null;
        unset($attrs['equal']['.id']);

        foreach ($this->tables[$table] ?? [] as $index => $row) {
            if (($row['.id'] ?? null) === $id) {
                $this->tables[$table][$index] = [...$row, ...$attrs['equal']];
                break;
            }
        }

        return [];
    }

    private function handleRemove(string $table, array $attrs): array
    {
        $id = $attrs['equal']['.id'] ?? $attrs['equal']['numbers'] ?? null;

        $this->tables[$table] = array_values(array_filter(
            $this->tables[$table] ?? [],
            fn (array $row) => ($row['.id'] ?? null) !== $id,
        ));

        return [];
    }

    /**
     * Mirrors RouterOS's `/move numbers=X destination=Y`: relocates row X to sit immediately
     * before row Y (or appends to the end when `destination` is omitted).
     */
    private function handleMove(string $table, array $attrs): array
    {
        $id = $attrs['equal']['numbers'] ?? null;
        $destination = $attrs['equal']['destination'] ?? null;
        $rows = $this->tables[$table] ?? [];

        $index = null;
        foreach ($rows as $i => $row) {
            if (($row['.id'] ?? null) === $id) {
                $index = $i;
                break;
            }
        }

        if ($index === null) {
            return [];
        }

        [$item] = array_splice($rows, $index, 1);

        $destIndex = null;
        if ($destination !== null) {
            foreach ($rows as $i => $row) {
                if (($row['.id'] ?? null) === $destination) {
                    $destIndex = $i;
                    break;
                }
            }
        }

        if ($destIndex === null) {
            $rows[] = $item;
        } else {
            array_splice($rows, $destIndex, 0, [$item]);
        }

        $this->tables[$table] = $rows;

        return [];
    }
}
