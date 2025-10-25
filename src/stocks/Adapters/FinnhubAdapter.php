<?php

namespace Stocks\Adapters;

use Stocks\PriceProviderAdapter;

class FinnhubAdapter implements PriceProviderAdapter
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct(string $apiKey, string $baseUrl = 'https://finnhub.io/api/v1')
    {
        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function fetchLiveQuotes(array $symbols): array
    {
        $out = [];
        foreach ($symbols as $symbol) {
            $symbol = strtoupper(trim($symbol));
            if ($symbol === '') {
                continue;
            }
            $url = sprintf('%s/quote?symbol=%s&token=%s', $this->baseUrl, rawurlencode($symbol), rawurlencode($this->apiKey));
            $json = $this->httpGet($url);
            if (!$json) {
                continue;
            }
            $data = json_decode($json, true);
            if (!is_array($data)) {
                continue;
            }
            $out[$symbol] = [
                'last' => isset($data['c']) ? (float)$data['c'] : null,
                'prev_close' => isset($data['pc']) ? (float)$data['pc'] : null,
                'high' => isset($data['h']) ? (float)$data['h'] : null,
                'low' => isset($data['l']) ? (float)$data['l'] : null,
                'volume' => isset($data['v']) ? (float)$data['v'] : null,
                'currency' => $data['currency'] ?? null,
                'provider_ts' => isset($data['t']) && $data['t'] ? date('c', (int)$data['t']) : date('c'),
            ];
        }
        return $out;
    }

    public function fetchDailyHistory(string $symbol, string $from, string $to): array
    {
        $fromTs = strtotime($from . ' 00:00:00');
        $toTs = strtotime($to . ' 23:59:59');
        if (!$fromTs || !$toTs) {
            return [];
        }
        $url = sprintf('%s/stock/candle?symbol=%s&resolution=D&from=%d&to=%d&token=%s',
            $this->baseUrl,
            rawurlencode(strtoupper($symbol)),
            $fromTs,
            $toTs,
            rawurlencode($this->apiKey)
        );
        $json = $this->httpGet($url);
        if (!$json) {
            return [];
        }
        $data = json_decode($json, true);
        if (!is_array($data) || ($data['s'] ?? '') !== 'ok') {
            return [];
        }
        $candles = [];
        $count = isset($data['t']) && is_array($data['t']) ? count($data['t']) : 0;
        for ($i = 0; $i < $count; $i++) {
            $candles[] = [
                'date' => date('Y-m-d', (int)$data['t'][$i]),
                'open' => isset($data['o'][$i]) ? (float)$data['o'][$i] : null,
                'high' => isset($data['h'][$i]) ? (float)$data['h'][$i] : null,
                'low' => isset($data['l'][$i]) ? (float)$data['l'][$i] : null,
                'close' => isset($data['c'][$i]) ? (float)$data['c'][$i] : null,
                'volume' => isset($data['v'][$i]) ? (float)$data['v'][$i] : null,
            ];
        }
        return $candles;
    }

    public function lookupMetadata(string $symbol): array
    {
        $url = sprintf('%s/stock/profile2?symbol=%s&token=%s',
            $this->baseUrl,
            rawurlencode(strtoupper($symbol)),
            rawurlencode($this->apiKey)
        );
        $json = $this->httpGet($url);
        if (!$json) {
            return [];
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return [];
        }
        return array_filter([
            'name' => $data['name'] ?? null,
            'exchange' => $data['exchange'] ?? null,
            'currency' => $data['currency'] ?? null,
            'sector' => $data['finnhubIndustry'] ?? null,
            'industry' => $data['ipo'] ?? null,
            'beta' => isset($data['beta']) ? (float)$data['beta'] : null,
        ], static fn($value) => $value !== null && $value !== '');
    }

    public function searchSymbols(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $url = sprintf('%s/search?q=%s&token=%s',
            $this->baseUrl,
            rawurlencode($query),
            rawurlencode($this->apiKey)
        );
        $json = $this->httpGet($url);
        if (!$json) {
            return [];
        }
        $data = json_decode($json, true);
        if (!is_array($data) || !isset($data['result']) || !is_array($data['result'])) {
            return [];
        }

        $allowedPatterns = '/STOCK|ETF|ETP|FUND|TRUST|ADR|REIT|NOTE|PREF|INDEX|ETN|EQUITY/i';

        $out = [];
        foreach ($data['result'] as $row) {
            $rawSymbol = strtoupper(trim((string)($row['symbol'] ?? '')));
            $displaySymbol = strtoupper(trim((string)($row['displaySymbol'] ?? '')));
            $symbol = $rawSymbol !== '' ? $rawSymbol : $displaySymbol;
            if ($displaySymbol !== '' && $displaySymbol !== $symbol) {
                $symbol = $displaySymbol;
            }
            if ($symbol === '') {
                continue;
            }
            $type = (string)($row['type'] ?? '');
            if ($type !== '' && !preg_match($allowedPatterns, $type)) {
                continue;
            }
            $name = trim((string)($row['description'] ?? ''));
            $mic = strtoupper(trim((string)($row['mic'] ?? '')));
            $exchange = $mic !== '' ? $mic : $displaySymbol;
            if ($exchange === '') {
                $exchange = null;
            }

            $currency = strtoupper(trim((string)($row['currency'] ?? '')));
            if ($currency === '') {
                $currency = null;
            }

            $out[] = [
                'symbol' => $symbol,
                'name' => $name !== '' ? $name : $symbol,
                'exchange' => $exchange,
                'market' => $mic !== '' ? $mic : null,
                'currency' => $currency,
                'type' => $type !== '' ? $type : null,
            ];
        }

        return $out;
    }

    private function httpGet(string $url): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_USERAGENT => 'MoneyMap-Stocks/1.0'
            ]);
            $out = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($out === false || $status !== 200) {
                return null;
            }
            return $out;
        }

        $context = stream_context_create([
            'http' => [
                'timeout' => 8,
                'user_agent' => 'MoneyMap-Stocks/1.0'
            ]
        ]);
        $out = @file_get_contents($url, false, $context);
        return $out !== false ? $out : null;
    }
}
