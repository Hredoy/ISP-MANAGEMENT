<?php

namespace App\Services;

use RouterOS\Client;
use RouterOS\Query;
use Exception;

class MikrotikService
{
    protected $client;

    /**
     * Establish a connection to the MikroTik Router
     */
    public function connect($host, $user, $pass, $port = 8728)
    {
        try {
            // Initialize the Client
            $this->client = new Client([
                'host' => $host,
                'user' => $user,
                'pass' => $pass,
                'port' => (int) $port,
                'timeout' => 5, // Prevent PHP from hanging if router is down
            ]);

            return $this->client;
        } catch (Exception $e) {
            // Log the error for the admin
            \Log::error("MikroTik Connection Failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get system resource data (CPU, RAM, Uptime)
     */
    public function getSystemStats($host, $user, $pass, $port = 8728)
    {
        $client = $this->connect($host, $user, $pass, $port);

        if (!$client) {
            return ['error' => 'OFFLINE', 'cpu-load' => 0];
        }

        try {
            $query = new Query('/system/resource/print');
            $response = $client->query($query)->read();

            return $response[0] ?? ['error' => 'NO_DATA'];
        } catch (Exception $e) {
            return ['error' => 'QUERY_FAILED'];
        }
    }

    /**
     * Get real-time interface traffic (Tx/Rx)
     */
    public function getInterfaceTraffic($host, $user, $pass, $interface = 'ether1')
    {
        $client = $this->connect($host, $user, $pass);
        if (!$client) return null;

        $query = (new Query('/interface/monitor-traffic'))
            ->equal('interface', $interface)
            ->equal('once', '');

        return $client->query($query)->read();
    }
}
