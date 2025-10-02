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
        $normalized = array_values(array_unique(array_map(static function ($symbol) {
            return strtoupper(trim((string)$symbol));
        }, $symbols)));

        $normalized = array_filter($normalized, static fn($symbol) => $symbol !== '');

        if (empty($normalized)) {
            return [];
        }

        if (function_exists('curl_multi_init')) {
            $multi = $this->fetchLiveQuotesCurlMulti($normalized);
            if ($multi !== null) {
                return $multi;
            }
        }

        return $this->fetchLiveQuotesSequential($normalized);
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

    /**
     * @param string[] $symbols
     * @return array<string, array{last:?float,prev_close:?float,high:?float,low:?float,volume:?float,currency:?string,provider_ts:?string}>
     */
    private function fetchLiveQuotesSequential(array $symbols): array
    {
        $out = [];
        foreach ($symbols as $symbol) {
            $quote = $this->decodeQuoteResponse($this->httpGet($this->quoteUrl($symbol)));
            if ($quote !== null) {
                $out[$symbol] = $quote;
            }
        }

        return $out;
    }

    /**
     * @param string[] $symbols
     * @return array<string, array{last:?float,prev_close:?float,high:?float,low:?float,volume:?float,currency:?string,provider_ts:?string}>|null
     */
    private function fetchLiveQuotesCurlMulti(array $symbols): ?array
    {
        $multiHandle = curl_multi_init();
        if ($multiHandle === false) {
            return null;
        }

        $handles = [];
        foreach ($symbols as $symbol) {
            $handle = curl_init($this->quoteUrl($symbol));
            if ($handle === false) {
                continue;
            }
            curl_setopt_array($handle, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_USERAGENT => 'MoneyMap-Stocks/1.0',
            ]);
            curl_multi_add_handle($multiHandle, $handle);
            $handles[$symbol] = $handle;
        }

        if (!$handles) {
            curl_multi_close($multiHandle);
            return [];
        }

        $running = null;
        do {
            $status = curl_multi_exec($multiHandle, $running);
            if ($status === CURLM_CALL_MULTI_PERFORM) {
                continue;
            }
            if ($status !== CURLM_OK) {
                break;
            }
            if ($running) {
                $selectResult = curl_multi_select($multiHandle, 1.0);
                if ($selectResult === -1) {
                    usleep(100000); // 100ms backoff when select fails
                }
            }
        } while ($running);

        $results = [];
        foreach ($handles as $symbol => $handle) {
            $httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
            $body = curl_multi_getcontent($handle);
            $quote = ($httpCode === 200) ? $this->decodeQuoteResponse(is_string($body) ? $body : null) : null;
            if ($quote !== null) {
                $results[$symbol] = $quote;
            }
            curl_multi_remove_handle($multiHandle, $handle);
            curl_close($handle);
        }

        curl_multi_close($multiHandle);

        return $results;
    }

    private function quoteUrl(string $symbol): string
    {
        return sprintf('%s/quote?symbol=%s&token=%s', $this->baseUrl, rawurlencode($symbol), rawurlencode($this->apiKey));
    }

    /**
     * @return array{last:?float,prev_close:?float,high:?float,low:?float,volume:?float,currency:?string,provider_ts:?string}|null
     */
    private function decodeQuoteResponse(?string $json): ?array
    {
        if (!$json) {
            return null;
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            return null;
        }

        return [
            'last' => isset($data['c']) ? (float)$data['c'] : null,
            'prev_close' => isset($data['pc']) ? (float)$data['pc'] : null,
            'high' => isset($data['h']) ? (float)$data['h'] : null,
            'low' => isset($data['l']) ? (float)$data['l'] : null,
            'volume' => isset($data['v']) ? (float)$data['v'] : null,
            'currency' => $data['currency'] ?? null,
            'provider_ts' => isset($data['t']) && $data['t'] ? date('c', (int)$data['t']) : date('c'),
        ];
    }
}
