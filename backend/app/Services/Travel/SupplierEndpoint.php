<?php

namespace App\Services\Travel;

use Illuminate\Validation\ValidationException;

class SupplierEndpoint
{
    public function validate(string $url, bool $documentation = false): string
    {
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        if (! filter_var($url, FILTER_VALIDATE_URL) || ($parts['scheme'] ?? '') !== 'https'
            || isset($parts['user']) || isset($parts['pass'])
            || (! $documentation && (isset($parts['query']) || isset($parts['fragment'])))
            || (isset($parts['port']) && $parts['port'] !== 443)
            || ! preg_match('/^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,63}$/D', $host)
            || preg_match('/(?:^|\.)(?:localhost|local|internal|test|invalid|example|onion)$/D', $host)
            || (! $documentation && (preg_match('~[^a-zA-Z0-9/_\-.]~', $parts['path'] ?? '') || str_contains($parts['path'] ?? '', '..')))) {
            throw ValidationException::withMessages(['base_url' => ['Օգտագործեք մատակարարի հանրային HTTPS հասցեն՝ առանց բանալիի, հարցման պարամետրերի կամ ներքին IP-ի։']]);
        }

        return rtrim($url, '/');
    }

    public function resolve(string $host): array
    {
        $records = dns_get_record($host, DNS_A | DNS_AAAA);

        return array_values(array_unique(array_filter(array_map(fn ($record) => $record['ip'] ?? $record['ipv6'] ?? null, $records ?: []))));
    }

    public function options(string $url): array
    {
        $this->validate($url);
        $host = parse_url($url, PHP_URL_HOST);
        $addresses = $this->resolve($host);
        if (! $addresses) {
            throw new \RuntimeException('Supplier host could not be resolved');
        }
        foreach ($addresses as $address) {
            if (! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE)
                || str_starts_with(strtolower($address), 'ff')
                || (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && (int) explode('.', $address)[0] >= 224)
                || str_starts_with(strtolower($address), '::ffff:')
                || str_starts_with($address, '64:ff9b:')
                || (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && (ip2long($address) & 0xFFC00000) === ip2long('100.64.0.0'))) {
                throw new \RuntimeException('Supplier address is not public');
            }
        }
        if (! extension_loaded('curl')) {
            throw new \RuntimeException('cURL is required for supplier integration');
        }
        $address = $addresses[0];

        // Pin the validated DNS result while preserving TLS hostname verification.
        return ['proxy' => '', 'allow_redirects' => false, 'curl' => [CURLOPT_RESOLVE => [$host.':443:'.(str_contains($address, ':') ? '['.$address.']' : $address)]]];
    }
}
