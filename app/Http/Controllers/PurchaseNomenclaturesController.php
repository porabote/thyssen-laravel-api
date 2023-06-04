<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\PurchaseRequest;
use App\Models\PurchaseNomenclatures;
use App\Models\File;
use Porabote\FullRestApi\Server\ApiTrait;
use Porabote\Uploader\Uploader;
use App\Models\History;
use App\Models\HistoryLocal;
use App\Models\Comment;
use App\Models\Config;
use App\Http\Components\Mailer\Mailer;
use App\Http\Components\Mailer\Message;
use App\Http\Controllers\ObserversController;
use Porabote\Auth\Auth;
use App\Http\Components\AccessLists;

class PurchaseNomenclaturesController extends Controller
{

    static $authAllows;
    private $authData = [];

    function __construct()
    {
        self::$authAllows = [
            '_isChangeRequestStatus',
            //'tmp_setObjects'
        ];
    }

    static function _isChangeRequestStatus($request_id)
    {
        $request = PurchaseRequest::find($request_id);
        $noms = PurchaseNomenclatures::where('request_id', $request_id)->get();

        foreach ($noms as $nom) {
            if ($request->status_id == 2) {
                $nom->status_id = 30;
            } else if ($request->status_id == 3) {
                $nom->status_id = 31;
            } else if ($request->status_id == 13) {
                $nom->status_id = 33;
            }

            $nom->update();
        }
    }

//    function tmp_setObjects()
//    {
//        $user = new \stdClass();
//        $user->account_alias = 'Solikamsk';//Thyssen   Solikamsk
//        \Porabote\Auth\Auth::setUser($user);
//
//        $nmcls = PurchaseNomenclatures::with('request')
//            ->get();
//
//        foreach ($nmcls as $nmcl) {
//            if ($nmcl->request) {
//                $nmcl->object_id = $nmcl->request->object_id;
//                $nmcl->update();
//            }
//        }
//    }

}