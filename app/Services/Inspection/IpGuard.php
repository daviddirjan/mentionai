<?php

namespace App\Services\Inspection;

final class IpGuard
{
    /** True only for globally routable unicast addresses (IPv4 and IPv6). */
    public static function isPublic(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = inet_pton($ip);
            $hex = bin2hex($packed);

            // IPv4-mapped (::ffff:a.b.c.d), NAT64 (64:ff9b::/96) and IPv4-compatible forms embed an IPv4 address.
            if (str_starts_with($hex, '00000000000000000000ffff')
                || str_starts_with($hex, '0064ff9b0000000000000000')
                || str_starts_with($hex, '000000000000000000000000')) {
                return self::isPublic(inet_ntop(substr($packed, 12)));
            }
            // 6to4 (2002::/16) and Teredo (2001::/32) tunnel to arbitrary IPv4 hosts.
            if (str_starts_with($hex, '2002') || str_starts_with($hex, '20010000')) {
                return false;
            }
        } else {
            [$a, $b] = array_map('intval', explode('.', $ip));
            if ($a === 100 && $b >= 64 && $b <= 127) { // carrier-grade NAT
                return false;
            }
            if ($a === 192 && $b === 0 || $a === 198 && ($b === 18 || $b === 19)) { // IETF protocol / benchmarking
                return false;
            }
        }

        return (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
}
