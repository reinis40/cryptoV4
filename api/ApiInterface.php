<?php
interface ApiInterface
{
    public function getApiData(string $url,array $parameters);
    public function getCryptoListings(): array;
    public function getCryptoBySymbol(string $symbol): ?array;
}



