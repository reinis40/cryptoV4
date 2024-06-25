<?php
class CoinMarketCapApi implements ApiInterface
{
    public string $apiKey;

    public function __construct($apiKey)
    {
        $this->apiKey = $apiKey;
    }

    public function getApiData(string $url, array $parameters): array
    {
        $headers = [
              'Accepts: application/json',
              'X-CMC_PRO_API_KEY: ' . $this->apiKey
        ];
        $qs = http_build_query($parameters);
        $request = "$url?$qs";
        $curl = curl_init();
        curl_setopt_array($curl, [
              CURLOPT_URL => $request,
              CURLOPT_HTTPHEADER => $headers,
              CURLOPT_RETURNTRANSFER => 1
        ]);

        $response = curl_exec($curl);
        curl_close($curl);

        if ($response === false) {
            throw new Exception('Error fetching data from API.');
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Error decoding API response: ' . json_last_error_msg());
        }

        return $data;
    }

    public function getCryptoListings(): array
    {
        $url = 'https://pro-api.coinmarketcap.com/v1/cryptocurrency/listings/latest';
        $parameters = [
              'start' => '1',
              'limit' => '10',
              'convert' => 'EUR'
        ];
        $response = $this->getApiData($url, $parameters);
        $cryptoList = [];

        foreach ($response['data'] as $cryptoData) {
            $crypto = [
                  'name' => $cryptoData['name'],
                  'symbol' => $cryptoData['symbol'],
                  'quote' => $cryptoData['quote']['EUR']['price'],
            ];
            $cryptoList[] = $crypto;
        }

        return $cryptoList;
    }


    public function getCryptoBySymbol(string $symbol): ?array
    {
        $url = 'https://pro-api.coinmarketcap.com/v1/cryptocurrency/listings/latest';
        $parameters = [
              'start' => '1',
              'limit' => '5000',
              'convert' => 'EUR'
        ];
        $response = $this->getApiData($url, $parameters);

        foreach ($response['data'] as $cryptoData) {
            if (strtoupper($cryptoData['symbol']) === strtoupper($symbol)) {
                return [
                      'name' => $cryptoData['name'],
                      'symbol' => $cryptoData['symbol'],
                      'quote' => $cryptoData['quote']['EUR']['price'],
                ];
            }
        }

        return null;
    }
}

