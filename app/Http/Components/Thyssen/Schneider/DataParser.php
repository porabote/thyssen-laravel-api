<?php
namespace App\Http\Components\Thyssen\Schneider;

use App\Http\Components\Thyssen\Schneider\Connect;
use App\Models\GuidsSchneider;

class DataParser
{

    private static $connection;

    static private $accountMap = [
        "TMCE" => [3, 4],
        "Norilsk" => [4],
        "Solikamsk" => [3],
    ];

    static function parseContractors($alias)
    {
        self::$connection = Connect::connect();

        $filesList = Connect::getFilesList('/Thyssen24/' . $alias . '/xml_out');


        if (isset($filesList["contractor"])) {
            //  $i = 0;
            foreach ($filesList["contractor"] as $filePath) {
                // if ($i > 20000000000) break;
                $xmlSchema = Connect::readFile($filePath);
                $simpleXml = \simplexml_load_string($xmlSchema);

                foreach (self::$accountMap[$alias] as $platfotmId) {
                    GuidsSchneider::create([
                        "guid" => $simpleXml->contractor->GUID,
                        "json_data" => json_encode($simpleXml),
                        "component_id" => 14,
                        'account_id' => $platfotmId,
                    ]);
                }
                // $i++;
                Connect::delete($filePath);
            }
        }

        Connect::disconnect();
    }

}

?>
