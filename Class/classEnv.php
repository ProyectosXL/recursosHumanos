<?php


class DotEnv
{
    /**
     * The directory where the .env file can be located.
     *
     * @var string
     */
    protected $path;


    public function __construct(string $path)
    {   
         
        if(!file_exists($path)) {
             throw new \InvalidArgumentException(sprintf('%s does not exist', $path));
        }
        $this->path = $path;
    }

    private function load() :void
    {
        if (!is_readable($this->path)) {
             throw new \RuntimeException(sprintf('%s file is not readable', $this->path));
        }

        $lines = file($this->path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {

            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }

    /**
     * Archivos .env en orden de prioridad (solo los que existen):
     *  1) htdocs/.env  -> el mismo que usa sistemas (principal)
     *  2) recursosHumanos/.env -> respaldo, solo para claves que no estén en el principal
     * load() no pisa claves ya cargadas, así que gana el primero.
     */
    public static function archivos(): array
    {
        $candidatos = [
            dirname(__DIR__, 2) . '/.env',
            dirname(__DIR__) . '/.env',
        ];
        return array_values(array_filter($candidatos, 'is_readable'));
    }

    public function listVars(){
        foreach (self::archivos() as $archivo) {
            (new DotEnv($archivo))->load();
        }

        $vars = array(

            'HOST_CENTRAL' => getenv('HOST_CENTRAL'),
            'HOST_LOCALES' => getenv('HOST_LOCALES'),
            'HOST_APPS' => getenv('HOST_APPS'),
            'DATABASE_CENTRAL' => getenv('DATABASE_CENTRAL'),
            'DATABASE_LOCALES' => getenv('DATABASE_LOCALES'),
            'DATABASE_TANGOBIS' => getenv('DATABASE_TANGOBIS'),
            'DATABASE_UY' => getenv('DATABASE_UY'),
            'DATABASE_SUC_UY' => getenv('DATABASE_SUC_UY'),
            'DATABASE_APPS' => getenv('DATABASE_APPS'),
            'USER' => getenv('USER'),
            'PASS' => getenv('PASS'),
            'PASS_LOCALES' => getenv('PASS_LOCALES'),
            'CHARACTER' => getenv('CHARACTER'),

            'ENV' => getenv('ENV'),

        );

        return $vars;


    }

}