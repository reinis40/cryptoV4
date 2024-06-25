<?php

use Carbon\Carbon;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\ConsoleOutput;

class TransactionLogger
{
    private PDO $db;

    public function __construct(string $dbFile)
    {
        $this->db = new PDO('sqlite:' . $dbFile);
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createTable();
    }

    private function createTable(): void
    {
        $this->db->exec("CREATE TABLE IF NOT EXISTS transactions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            type TEXT NOT NULL,
            symbol TEXT NOT NULL,
            amount REAL NOT NULL,
            price REAL NOT NULL,
            date TEXT NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )");
    }

    public function logTransaction(int $user_id, string $type, string $symbol, float $amount, float $price): void
    {
        $date = Carbon::now()->toDateTimeString();
        $query = "INSERT INTO transactions (user_id, type, symbol, amount, price, date) VALUES (:user_id, :type, :symbol, :amount, :price, :date)";
        $stmt = $this->db->prepare($query);
        $stmt->execute([
              ':user_id' => $user_id,
              ':type' => $type,
              ':symbol' => $symbol,
              ':amount' => $amount,
              ':price' => $price,
              ':date' => $date,
        ]);
    }

    public function showTransactions(): void
    {
        $stmt = $this->db->query("SELECT * FROM transactions ORDER BY date DESC");
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $output = new ConsoleOutput();
        $table = new Table($output);
        $table->setHeaders(['TYPE', 'AMOUNT', 'SYMBOL', 'PRICE', 'DATE']);

        foreach ($transactions as $log) {
            $table->addRow([
                  $log['type'],
                  $log['amount'],
                  $log['symbol'],
                  "€ " . $log['price'],
                  $log['date'],
            ]);
        }
        $table->render();
    }
}

