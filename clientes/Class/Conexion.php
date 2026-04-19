<?php
// Class/Conexion.php

class Conexion {

    public static function errorMsg(): string {
        $errors = sqlsrv_errors();
        return $errors ? implode('; ', array_column($errors, 'message')) : 'Error desconocido';
    }

    public function conectar($host, $dbName, $user, $pass) {
        $connectionInfo = [
            'Database'               => $dbName,
            'UID'                    => $user,
            'PWD'                    => $pass,
            'CharacterSet'           => 'UTF-8',
            'Encrypt'                => false,
            'TrustServerCertificate' => true,
        ];

        $cid = sqlsrv_connect($host, $connectionInfo);

        if (!$cid) {
            throw new Exception("Imposible conectarse a {$dbName}: " . self::errorMsg());
        }

        return $cid;
    }

    public function conexionServidor() {
        return $this->conectar(DB_HOST_SERVIDOR, DB_NAME_SERVIDOR, DB_USER_SERVIDOR, DB_PASS_SERVIDOR);
    }

    public function conexionLakerbis() {
        return $this->conectar(DB_HOST_LOCALES, DB_NAME_LOCALES, DB_USER_LOCALES, DB_PASS_LOCALES);
    }

    public function conexionLocal($nombreDB) {
        return $this->conectar(DB_HOST_SERVIDOR, $nombreDB, DB_USER_SERVIDOR, DB_PASS_SERVIDOR);
    }

    public function conexionSucursalPorHost($host, $dbName, $user, $pass) {
        return $this->conectar($host, $dbName, $user, $pass);
    }
}
