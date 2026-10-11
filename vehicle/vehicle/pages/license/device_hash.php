<?php
/**
 * Generate a consistent SHA-256 hash to uniquely identify the current machine.
 * Uses OS info + MAC address (+ disk serial as fallback).
 */
function generateDeviceHash(): string
{
    $os   = PHP_OS_FAMILY;
    $info = php_uname(); // baseline OS + machine info
    $mac  = '';

    if ($os === 'Windows') {
        // Windows: try getmac
        $output = @shell_exec('getmac 2>NUL');
        if ($output && preg_match('/([A-F0-9]{2}[:-]){5}[A-F0-9]{2}/i', $output, $m)) {
            $mac = $m[0];
        }

        // Fallback: disk serial
        if (!$mac) {
            $serial = @shell_exec('wmic diskdrive get SerialNumber 2>NUL');
            if ($serial) {
                $mac = trim($serial);
            }
        }

    } elseif ($os === 'Linux' || $os === 'Darwin') {
        // Linux/macOS: get default interface
        $iface = trim(@shell_exec("ip route get 8.8.8.8 2>/dev/null | awk '{print $5}'"));
        if ($iface && file_exists("/sys/class/net/{$iface}/address")) {
            $mac = trim(file_get_contents("/sys/class/net/{$iface}/address"));
        }

        // macOS fallback
        if (!$mac && $os === 'Darwin') {
            $mac = trim(@shell_exec("ifconfig en0 | awk '/ether/{print $2}'"));
        }

        // Linux disk serial fallback
        if (!$mac && $os === 'Linux') {
            $serial = @shell_exec("lsblk -o SERIAL -n 2>/dev/null | head -n1");
            if ($serial) {
                $mac = trim($serial);
            }
        }
    }

    // Normalize & validate
    if ($mac) {
        $mac = strtolower(str_replace('-', ':', $mac));
        if (preg_match('/^(00:00:00|ff:ff:ff)/', $mac)) {
            $mac = '';
        }
    }

    // Final fallback
    if (!$mac) {
        $mac = gethostname();
    }

    // Hash of OS info + identifier
    return hash('sha256', $info . $mac);
}
