<?php

namespace App\Services\Olt;

use App\Models\Olt;
use RuntimeException;
use Throwable;

/**
 * VSOL (and other SNMP-managed) OLT ONU authorization via SNMP SET
 * against the vendor's private ONU-auth MIB. OIDs below are placeholders
 * following VSOL's documented private MIB structure — confirm the exact
 * OID tree for the target OLT firmware before production use.
 */
class VsolOltDriver implements OltDriverInterface
{
    private const OID_ONU_AUTH_TABLE = '1.3.6.1.4.1.37950.1.1.5.1.1.1';

    public function __construct(private readonly Olt $olt) {}

    public function testConnection(): array
    {
        if (! extension_loaded('snmp')) {
            return ['ok' => false, 'message' => 'PHP snmp extension is not installed on this server.'];
        }

        try {
            $result = @snmpget($this->olt->host, $this->olt->snmp_community ?: 'public', '1.3.6.1.2.1.1.1.0', 3000000, 1);

            if ($result === false) {
                return ['ok' => false, 'message' => 'SNMP request failed or timed out.'];
            }

            return ['ok' => true, 'message' => 'CONNECTION_OK'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function bindOnu(array $params): array
    {
        $serial = $params['serial'] ?? $params['mac'] ?? null;

        if (! $serial) {
            return ['ok' => false, 'message' => 'ONU serial number or MAC is required for VSOL binding.'];
        }

        if (! extension_loaded('snmp')) {
            return ['ok' => false, 'message' => 'PHP snmp extension is not installed on this server.'];
        }

        $onuId = $params['onuId'] ?? '1';
        $oid = self::OID_ONU_AUTH_TABLE.'.'.$params['ponPort'].'.'.$onuId;

        try {
            $result = @snmpset(
                $this->olt->host,
                $this->olt->snmp_community ?: 'private',
                $oid,
                's',
                $serial,
                3000000,
                1
            );

            if ($result === false) {
                throw new RuntimeException('SNMP SET failed for ONU authorization.');
            }

            return ['ok' => true, 'message' => 'ONU_BOUND'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function unbindOnu(array $params): array
    {
        if (! extension_loaded('snmp')) {
            return ['ok' => false, 'message' => 'PHP snmp extension is not installed on this server.'];
        }

        $onuId = $params['onuId'] ?? '1';
        $oid = self::OID_ONU_AUTH_TABLE.'.'.$params['ponPort'].'.'.$onuId;

        try {
            $result = @snmpset($this->olt->host, $this->olt->snmp_community ?: 'private', $oid, 's', '', 3000000, 1);

            if ($result === false) {
                throw new RuntimeException('SNMP SET failed while clearing ONU authorization.');
            }

            return ['ok' => true, 'message' => 'ONU_UNBOUND'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }
}
