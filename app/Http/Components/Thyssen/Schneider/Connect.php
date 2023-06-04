<?php
namespace App\Http\Components\Thyssen\Schneider;

class Connect {

    public static $connection;

    static private $host = 'ftp.schneider-group.com';
    static private $user = 'thyssen24';
    static private $psw = 'wDnV86W6';
    static private $port = '21';

    static function connect()
    {
        try {
            self::$connection = ftp_ssl_connect(self::$host, self::$port, 10);
            $loginResult = ftp_login(self::$connection, self::$user, self::$psw);
            ftp_pasv(self::$connection, true);

            if (!$loginResult) {
                die("Не удалось подключиться к серверу");
            }

            return self::$connection;

        } catch (Exception\ExceptionFtpsConnect $e) {
            return $e->connectError();
        }

    }

    static function disconnect()
    {
        ftp_close(self::$connection);
    }

    static function getFilesList($path)
    {
        $filesList = ftp_nlist(self::$connection, $path);

        $filesListSorted = [];
        foreach ($filesList as $filePath)
        {
            $filePrefix = explode('_', pathinfo($filePath)['filename'])[0];
            $filesListSorted[$filePrefix][] = $filePath;
        }

        return $filesListSorted;
    }

    static function readFile($path)
    {
        $streamData = fopen('php://temp', 'r+');

        ftp_fget(self::$connection, $streamData, $path, FTP_ASCII, 0);
        $fstats = fstat($streamData);
        fseek($streamData, 0);
        $contents = fread($streamData, $fstats['size']);
        fclose($streamData);

        return $contents;
    }

    static function delete($filePath)
    {
        if (!ftp_delete(self::$connection, $filePath)) {
           // TODO EXCEPTION
        }
    }

}
