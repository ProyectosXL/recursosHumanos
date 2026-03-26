<?php
// Class/Conexion.php

class Conexion {
    
    public function conectar($host, $dbName, $user, $pass) {
        $driver = DB_DRIVER;
        
        $connection_string = "DRIVER={$driver};Server={$host};DATABASE={$dbName}";
        
        $cid = @odbc_connect($connection_string, $user, $pass);

        if (!$cid) {
            throw new Exception("Imposible conectarse a la base de datos: " . odbc_errormsg());
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