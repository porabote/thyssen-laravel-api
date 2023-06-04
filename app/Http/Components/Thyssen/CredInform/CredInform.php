<?php

namespace App\Http\Components\Thyssen\CredInform;


class CredInform
{

    static private $url = 'https://restapi.credinform.ru';
    static private $login = 'credlogin@ro.ru';
    static private $psw = '123456';
    static private $accessKey;


    static function getAccessKey() {
        if (!self::$accessKey) {
            self::$accessKey = self::setAccessKey();
        }

        return self::$accessKey;
    }

    static function setAccessKey()
    {
        $data = [
            'url' => self::$url,
            'uri' => '/api/Authorization/GetAccessKey',
            'response' => [
                'username' => self::$login,
                'password' => self::$psw
            ]
        ];

        $ch = curl_init($data['url'] . $data['uri']);
        curl_setopt_array($ch, [
            CURLOPT_URL => $data['url'] . $data['uri'],
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_HEADER => 0, // allow return headers
            CURLOPT_HTTPHEADER => ['accept: text/plain'],
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_COOKIESESSION => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data['response'])
        ]);

        $output = curl_exec($ch);
        curl_close($ch);

        return json_decode($output, true)['accessKey'];
    }

    static function getCompanyInfo($taxNumber, $statisticalNumber)
    {
        $accessKey = self::getAccessKey();

        $data = '{
          "language": "Russian",
          "searchCompanyParameters": {
	          statisticalNumber: "' . $statisticalNumber . '",
	          companyName : "",	
              taxNumber: "' . $taxNumber . '",
              includeBranch: "true"
          }
        }';

        $ch = curl_init(self::$url . '/api/Search/SearchCompany');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_HEADER => 0, // allow return headers
            CURLOPT_HTTPHEADER => ['accept: text/plain', 'accessKey: ' . $accessKey, 'Content-Type: application/json-patch+json'],
            CURLOPT_COOKIESESSION => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data
        ]);

        $output = curl_exec($ch);//debug($output);
        curl_close($ch);

        return json_decode($output, true)['companyDataList'][0];
    }

    static function getBlobData($shema, $section = '/api/Report/GetFile')
    {
        $accessKey = self::getAccessKey();

        $ch = curl_init(self::$url . $section);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_HEADER => 0, // allow return headers
            CURLOPT_HTTPHEADER => ['accept: text/plain', 'accessKey: ' . $accessKey, 'Content-Type: application/json-patch+json'],
            CURLOPT_COOKIESESSION => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $shema
        ]);

        $output = curl_exec($ch);
        curl_close($ch);

        return json_decode($output);
    }

    static function getFilePdf($id)
    {
        $accessKey = self::getAccessKey();

        $data =
            '
        {
          "language": "Russian",
          "companyId": "' . $id . '",
          "period": {
              "from": "2001-01-01T00:00:00"
          },
          "sectionKeyList": [
	        "ContactData", 
	        "RegData",
	        "FirstPageFinData",
	        "Rating",
	        "CompanyName",
	        "UserVerification",
	        "LeadingFNS",	        	        	         
	        "ShareFNS",	
	        "Subs_short",	
	        "SRO",
	        "Pledges",	
	        "ArbitrageInNewFormat",
	        "EnforcementProceeding",
	        "FinancialEconomicIndicators"
          ]
        }';

        $ch = curl_init(self::$url . '/api/Report/GetFile');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_HEADER => 0, // allow return headers
            CURLOPT_HTTPHEADER => ['accept: text/plain', 'accessKey: ' . $accessKey, 'Content-Type: application/json-patch+json'],
            CURLOPT_COOKIESESSION => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data
        ]);

        $output = curl_exec($ch);
        curl_close($ch);

        return json_decode($output);
    }


    static function get($inn = null, $name = null, $okpo = null)
    {
        $accessKey = self::getAccessKey();

        if (!$inn && !$name) return [];

        $data = [
            "language" => "Russian",
            "searchCompanyParameters" => [
                "statisticalNumber" => $okpo,
                "companyName" => $name,
                "taxNumber" => $inn,
                "includeBranch" => true,
            ]
        ];
        $data = json_encode($data);

//        $data =
//            '{
//          "language": "Russian",
//          "searchCompanyParameters": {
//	          statisticalNumber: "' . $okpo . '",
//
//              includeBranch: "true"
//          }
//        }';
//	          companyName : "'.$name.'",
//              taxNumber: "' . $inn . '",

        $ch = curl_init(self::$url . '/api/Search/SearchCompany');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_HEADER => 0, // allow return headers
            CURLOPT_HTTPHEADER => ['accept: text/plain', 'accessKey: ' . $accessKey, 'Content-Type: application/json-patch+json'],
            CURLOPT_COOKIESESSION => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data
        ]);

        $output = curl_exec($ch);//debug($output);
        curl_close($ch);
        return json_decode($output, true);
    }

}
