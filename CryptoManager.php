<?php

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\ConsoleOutput;

class CryptoManager
{
    private ApiInterface $api;
    private PDO $db;
    private User $user;
    private TransactionLogger $logger;
    private array $wallet;
    private int $initialEur;

    public function __construct(ApiInterface $api, PDO $db, User $user, TransactionLogger $logger)
    {
        $this->api = $api;
        $this->db = $db;
        $this->user = $user;
        $this->logger = $logger;
        $this->initializeWallet();
    }
    private function initializeWallet(): void
    {
        $stmt = $this->db->prepare("SELECT * FROM wallet WHERE user_id = :user_id AND currency = 'EUR'");
        $stmt->bindParam(':user_id', $this->user->id);
        $stmt->execute();
        $walletData = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($walletData) {
            $this->wallet = [
                  'EUR' => [
                        'amount' => $walletData['amount'],
                        'bought_price' => $walletData['bought_price']
                  ]
            ];
        } else {

            $this->wallet = [
                  'EUR' => [
                        'amount' => $this->initialEur,
                        'bought_price' => 1
                  ]
            ];

            $stmt = $this->db->prepare("INSERT INTO wallet (user_id, currency, amount, bought_price) VALUES (:user_id, 'EUR', :amount, :bought_price)");
            $stmt->bindParam(':user_id', $this->user->id);
            $stmt->bindParam(':amount', $this->initialEur);
            $stmt->bindParam(':bought_price', $this->wallet['EUR']['bought_price']);
            $stmt->execute();
        }
    }
    public function showCrypto(): void
    {
        $data = $this->api->getCryptoListings();
        $output = new ConsoleOutput();
        $table = new Table($output);
        $table->setHeaders(['Name', 'Symbol', 'Price per 1 coin (EUR)']);

        foreach ($data as $crypto) {
            $table->addRow([
                  $crypto['name'],
                  $crypto['symbol'],
                  "€" . $crypto['quote']
            ]);
        }
        $table->render();
    }

    public function buyCrypto($symbol, $amountEUR): void
    {
        $data = $this->api->getCryptoListings();

        $price = null;
        foreach ($data as $crypto) {
            if ($crypto['symbol'] == strtoupper($symbol)) {
                $price = $crypto['quote'];
                break;
            }
        }

        if ($price !== null) {
            $amountCrypto = $amountEUR / $price;

            $stmt = $this->db->prepare("SELECT amount FROM wallet WHERE user_id = :user_id AND currency = 'EUR'");
            $stmt->bindParam(':user_id', $this->user->id);
            $stmt->execute();
            $currentEurAmount = $stmt->fetchColumn();

            if ($currentEurAmount >= $amountEUR) {
                $newEurAmount = $currentEurAmount - $amountEUR;

                $this->wallet['EUR']['amount'] = $newEurAmount;

                $stmt = $this->db->prepare("UPDATE wallet SET amount = :amount WHERE user_id = :user_id AND currency = 'EUR'");
                $stmt->bindParam(':amount', $newEurAmount);
                $stmt->bindParam(':user_id', $this->user->id);
                $stmt->execute();

                if (!isset($this->wallet[$symbol])) {
                    $this->wallet[$symbol] = ['amount' => 0, 'bought_price' => $price];
                }
                $this->wallet[$symbol]['amount'] += $amountCrypto;

                $stmt = $this->db->prepare("INSERT INTO wallet (user_id, currency, amount, bought_price) 
                                        VALUES (:user_id, :currency, :amount, :bought_price)
                                        ON CONFLICT(user_id, currency) DO UPDATE 
                                        SET amount = amount + :amount, bought_price = :bought_price");
                $stmt->bindParam(':user_id', $this->user->id);
                $stmt->bindParam(':currency', $symbol);
                $stmt->bindParam(':amount', $amountCrypto);
                $stmt->bindParam(':bought_price', $price);
                $stmt->execute();

                $this->logger->logTransaction($this->user->id, 'buy', $symbol, $amountCrypto, $amountEUR);
                echo "Bought $amountCrypto of $symbol at €$price each.\n";
            } else {
                echo "Insufficient funds to buy €$amountEUR of $symbol.\n";
            }
        } else {
            echo "Error: Crypto quote not found for symbol '$symbol'.\n";
        }
    }

    public function sellCrypto($symbol): void
    {
        $data = $this->api->getCryptoListings();
        $price = null;
        foreach ($data as $crypto) {
            if ($crypto['symbol'] === strtoupper($symbol)) {
                $price = $crypto['quote'];
                break;
            }
        }
        if ($price !== null) {
            $stmt = $this->db->prepare("SELECT amount FROM wallet WHERE user_id = :user_id AND currency = :currency");
            $stmt->bindParam(':user_id', $this->user->id);
            $stmt->bindParam(':currency', $symbol);
            $stmt->execute();
            $amountCrypto = $stmt->fetchColumn();
            $stmt = $this->db->prepare("DELETE FROM wallet WHERE user_id = :user_id AND currency = :currency");
            $stmt->bindParam(':user_id', $this->user->id);
            $stmt->bindParam(':currency', $symbol);
            $stmt->execute();
            $eurValue = $amountCrypto * $price;
            $stmt = $this->db->prepare("UPDATE wallet SET amount = amount + :eurValue WHERE user_id = :user_id AND currency = 'EUR'");
            $stmt->bindParam(':user_id', $this->user->id);
            $stmt->bindParam(':eurValue', $eurValue);
            $stmt->execute();
            $this->logger->logTransaction($this->user->id, 'sell', $symbol, $amountCrypto, $eurValue);
            echo "Sold $amountCrypto of $symbol at €$price each.\n";
        }
    }
    public function showWallet(): void
    {
        $output = new ConsoleOutput();
        $table = new Table($output);
        $table->setHeaders(['Currency', 'Amount', 'Value in EUR', 'Bought Price', 'Profit/Loss (%)']);
        $stmt = $this->db->prepare("SELECT * FROM wallet WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $this->user->id);
        $stmt->execute();
        $walletData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($walletData as $walletEntry) {
            $symbol = $walletEntry['currency'];
            $amount = $walletEntry['amount'];
            $boughtPrice = $walletEntry['bought_price'];
            $currentPrice = $symbol === 'EUR' ? 1 : $this->getCurrentPrice($symbol);
            $valueInEur = $amount * $currentPrice;
            $profitLoss = $currentPrice && $boughtPrice ? (($currentPrice - $boughtPrice) / $boughtPrice) * 100 : 0;
            $table->addRow([
                  $symbol,
                  $amount,
                  number_format($valueInEur, 2),
                  number_format($boughtPrice, 2),
                  $symbol === 'EUR' ? '-' : number_format($profitLoss, 2) . '%'
            ]);
        }
        $table->render();
    }
    private function getCurrentPrice($symbol)
    {
        $data = $this->api->getCryptoListings();
        foreach ($data as $crypto) {
            if ($crypto['symbol'] === ($symbol)) {
                return (float)$crypto['quote'];
            }
        }
        return 0;
    }
}

