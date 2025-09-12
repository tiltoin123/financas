<?php

namespace model;

use JsonSerializable;
use Exception;
use db\Mysql;
use function json_decode;
use function strlen;

class User implements JsonSerializable
{
    const STATUS = [
        1 => 'PENDING',
        2 => 'ACTIVE',
        3 => 'INACTIVE',
    ];

    private int $id;
    private string $name;
    private string $email;
    private string $phone;
    private $user;
    private string $password;
    private int $status;
    private string $createdAt;
    private string $updatedAt;

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'user' => $this->user,
            'status' => $this->status,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }

    public function __construct(int $id)
    {
        if ($id) {
            $con = Mysql::connection();
            $query = <<<QUERY
            SELECT id,name,email,phone,user,status,created_at,updated_at
            FROM usuario WHERE codigo = ?
            QUERY;
            $result = $con->execute_query($query, [$id]);
            while ($row = $result->fetch_assoc()) {
                $this->id = $row['id'];
                $this->name = $row['name'];
                $this->email = $row['email'];
                $this->phone = $row['phone'];
                $this->user = $row['user'];
                $this->status = $row['status'];
                $this->createdAt = $row['created_at'];
                $this->updatedAt = $row['updated_at'];
            }
        }
    }
}
