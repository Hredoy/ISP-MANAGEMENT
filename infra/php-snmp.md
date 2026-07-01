# PHP SNMP Setup For OLT Integration

Install on Ubuntu/Debian VPS:

```bash
sudo apt update
sudo apt install -y snmp php-snmp
sudo systemctl restart php8.2-fpm
sudo systemctl restart nginx
php -m | grep snmp
```

For another PHP version, install the matching package, for example `php8.3-snmp`.

Used OLT discovery OID:

```text
sysDescr.0 = 1.3.6.1.2.1.1.1.0
```

Supported vendor families:

- Huawei MA5608T / MA5683T via Huawei GPON private MIB.
- ZTE C300 / C320 via ZTE private MIB, with Telnet/SSH provisioning fallback.
- BDCOM P3310 via BDCOM private MIB.
- VSOL existing SNMP path retained.

Signal color thresholds:

```text
green  / 🟢: RX > -25 dBm
yellow / 🟡: RX -25 to -27 dBm
red    / 🔴: RX < -27 dBm
```
