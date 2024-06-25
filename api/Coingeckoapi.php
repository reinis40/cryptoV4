<?php
class CoingeckoApi implements ApiInterface
{
    private $baseUrl = 'https://api.coingecko.com/api/v3';
    private $apiKey;

    public function __construct($apiKey = null)
    {
        $this->apiKey = $apiKey;
    }

    public function getApiData($url, $parameters)
    {
        $url = $this->baseUrl . $url;
        if (!empty($parameters)) {
            $url .= '?' . http_build_query($parameters);
        }

        $curl = curl_init();
        curl_setopt_array($curl, array(
              CURLOPT_URL => $url,
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_FOLLOWLOCATION => true,
              CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'User-Agent: YourApp/1.0',
                    'Authorization: Bearer ' . $this->apiKey
              )
        ));

        $response = curl_exec($curl);
        if ($response === false) {
            die('Curl error: ' . curl_error($curl));
        }

        curl_close($curl);

        $decodedResponse = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            die('Error decoding JSON: ' . json_last_error_msg());
        }

        return $decodedResponse;
    }

    public function getCryptoListings(): array
    {
        $url = '/coins/markets';
        $parameters = [
              'vs_currency' => 'eur',
              'order' => 'market_cap_desc',
              'per_page' => 10,
              'page' => 1,
              'sparkline' => false
        ];

        $response = $this->getApiData($url, $parameters);
        $cryptoList = [];

        foreach ($response as $cryptoData) {
            $crypto = [
                  'name' => $cryptoData['name'],
                  'symbol' => strtoupper($cryptoData['symbol']),
                  'quote' => $cryptoData['current_price']
            ];
            $cryptoList[] = $crypto;
        }

        return $cryptoList;
    }

    public function getCryptoBySymbol(string $symbol): ?array
    {
        $url = '/coins/markets';
        $parameters = [
              'vs_currency' => 'eur',
              'order' => 'market_cap_desc',
              'per_page' => 250,
              'page' => 1,
              'sparkline' => false
        ];

        $response = $this->getApiData($url, $parameters);

        foreach ($response as $cryptoData) {
            if (strtoupper($cryptoData['symbol']) === strtoupper($symbol)) {
                return [
                      'name' => $cryptoData['name'],
                      'symbol' => strtoupper($cryptoData['symbol']),
                      'quote' => $cryptoData['current_price']
                ];
            }
        }

        return null;
    }
}









