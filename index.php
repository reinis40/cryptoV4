<?php
require 'vendor/autoload.php';
require 'api/ApiInterface.php';
require 'api/CoinMarketCapApi.php';
require 'api/CoingeckoApi.php';
require 'storage/TransactionLogger.php';
require 'CryptoManager.php';
require 'User.php';

$dbFile = 'storage/database.sqlite';
$pdo = new PDO('sqlite:' . $dbFile);
$user = new User($pdo);
$logger = new TransactionLogger($dbFile);
echo "Login:\n";
$username = readline("Username: ");
$password = readline("Password: ");

$api = new CoinMarketCapApi("ccb58a8c-61b0-4c84-8289-5e562a8476a1");


if (!$user->login($username, $password)) {
    exit("Invalid username or password.\n");
}
$cryptoManager = new CryptoManager($api, $pdo, $user, $logger);

while (true) {
    echo "\n1. List of crypto\n2. Buy\n3. Sell\n4. View wallet\n5. View logs\n6. Exit\n";
    $input = readline("Select an option: ");

    switch ($input) {
        case 1:
            $cryptoManager->showCrypto();
            break;
        case 2:
            $symbol = readline("Enter cryptocurrency symbol: ");
            $amountEUR = readline("Enter amount in EUR to buy: ");
            $cryptoManager->buyCrypto(strtoupper($symbol), (float)$amountEUR);
            break;
        case 3:
            $symbol = readline("Enter cryptocurrency symbol: ");
            $cryptoManager->sellCrypto(strtoupper($symbol));
            break;
        case 4:
            $cryptoManager->showWallet();
            break;
        case 5:
            $logger->showTransactions();
            break;
        case 6:
            exit("Goodbye!\n");
        default:
            echo "Invalid option, please try again.\n";
    }
}




