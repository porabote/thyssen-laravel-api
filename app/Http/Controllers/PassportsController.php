<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Porabote\FullRestApi\Server\ApiTrait;
use Porabote\Uploader\Uploader;
use App\Models\Passport;
use App\Models\ApiUsers;
use Porabote\Stringer\Stringer;

class PassportsController extends Controller
{
    function getByUser($request, $userId)
    {
        $passport = Passport::where('user_id', $userId)
            ->where('type', 'russian')
            ->get()
            ->first();

        if (!$passport) {
            $passport = self::_addPassport($userId);
        }

        return response()->json([
            'data' => $passport,
            'meta' => []
        ]);
    }

    public static function _addPassport($userId)
    {
        $user = ApiUsers::find($userId)->toArray();
        $fi = explode(' ', $user['name']);

        return Passport::create([
            'name' => $fi[1],
            'last_name' => $fi[0],
            'patronymic' => $user['patronymic'],
            'user_id' => $user['id'],
            'type' => 'russian',
        ]);

    }

    function getForeignByUser($request, $userId)
    {
        $passport = Passport::where('user_id', $userId)
            ->where('type', 'foreign')
            ->get()
            ->first();

        if (!$passport) {
            $passport = self::_addForeignPassport($userId);
        }

        return response()->json([
            'data' => $passport,
            'meta' => []
        ]);
    }

    public static function _addForeignPassport($userId)
    {
        $user = ApiUsers::find($userId)->toArray();
        $fi = explode(' ', $user['name']);

        return Passport::create([
            'name_en' => ucfirst(Stringer::transcript($fi[0])),
            'last_name_en' => ucfirst(Stringer::transcript($fi[1])),
            'patronymic' => ucfirst(Stringer::transcript($user['patronymic'])),
            'user_id' => $user['id'],
            'type' => 'foreign',
        ]);

    }

    function edit($request)
    {
        $data = $request->all();

        $passport = Passport::find($data['id']);

        $attrs = $passport->getAttributes();

        foreach ($attrs as $attr => $value) {
            if (array_key_exists($attr, $data)) {
                $passport->$attr = $data[$attr];
            }
        }

        $passport->update();

        return response()->json([
            'data' => $passport->toArray(),
            'meta' => []
        ]);
    }
}