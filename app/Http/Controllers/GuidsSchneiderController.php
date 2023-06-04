<?php
namespace App\Http\Controllers;

use Porabote\Auth\Auth;
use App\Models\GuidsSchneider;
use App\Models\Contractors;

class GuidsSchneiderController extends Controller
{
    static $authAllows;
    private $authData = [];

    function __construct()
    {
        self::$authAllows = [
            'assignContractors',
            'test'
        ];
    }

    function assignContractors()
    {
       // debug(Auth::$user->account_id);
        $guids = GuidsSchneider::where('account_id', Auth::$user->account_id)
            ->where('status', 0)
            ->limit(300)
            ->get();

        foreach ($guids as $guid) {
            $data = json_decode($guid->json_data, true);

            $contractor = Contractors::where('inn', $data['contractor']['INN'])
                ->where('kpp', $data['contractor']['KPP'])
                ->get()
                ->first();
            if ($contractor) {

                $contractor->guid_schneider_id = $guid->id;
                $contractor->update();

                $guid->foreign_key = $contractor->id;
                $guid->status = 1;
                $guid->update();
            }

        }

        return response()->json([
            'data' => $contractor,
            'meta' => []
        ]);

    }
}
