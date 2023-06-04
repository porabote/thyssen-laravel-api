<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Porabote\FullRestApi\Server\ApiTrait;
use App\Models\BusinessEvents;
use App\Models\Cities;

class CitiesController extends Controller
{
    use ApiTrait;

    static $authAllows;
    private $authData = [];

    function __construct()
    {
        self::$authAllows = [
            'convertNames',
        ];
    }

    function convertNames()
    {
        $results = DB::select(DB::raw("
            SELECT * FROM dictionaries.airports 
            WHERE countryName = 'KAZAKHSTAN'"
        )); // TURKEY // GERMANY
       // debug($results);

//        $ruCities = DB::select(DB::raw("
//            SELECT * FROM dictionaries.cities_ru
//            "
//        ));
//        foreach ($results as $city) {
//            $city = (array) $city;
//            Cities::create([
//                'code' => $city['cityCode'],
//                'country_code' => $city['countryCode'],
//                'name_ru' => $city['cityName'],
//                'name_en' => $city['cityName'],
//        ]);
//        }
    }

}

?>