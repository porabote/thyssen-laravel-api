<?php
namespace App\Services;

class ClientAuthService {

    public function start(array $data, $method = 'getAuthData') : string {
        if (method_exists($this, $method)) {
            $this->$method($data['name'], $data['surname']);
        }

        throw new \Exception("Method doesn't exists", 404);
    }

    protected function getAuthData(string $name, string $surname) {
        return $name + $surname;
    }

}
