<?php
class User
{
    private PDO $db;
    public int $id;
    public string $username;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function register(string $username, string $password): bool
    {
        $hashedPassword = md5($password);
        $stmt = $this->db->prepare("INSERT INTO users (username, password) VALUES (:username, :password)");
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':password', $hashedPassword);
        return $stmt->execute();
    }

    public function login(string $username, string $password): bool
    {
        $hashedPassword = md5($password);
        $stmt = $this->db->prepare("SELECT id FROM users WHERE username = :username AND password = :password");
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $this->id = $user['id'];
            $this->username = $username;
            return true;
        }
        return false;
    }
}

