<?php
namespace App\Http\Components;

use App\Models\AccessListsUsers;
use App\Models\AccessListsUsersContractors;
use Porabote\Auth\Auth;

class AccessLists {

    static function _check($list_id)
    {
        $access = AccessListsUsers::where('access_list_id', $list_id)
            ->where('user_id', Auth::$user->id)
            ->where('account_id', Auth::$user->account_id)
            ->get()
            ->toArray();

        return ($access) ? true : false;
    }

    static function _get($list_id)
    {
        $acceptors =  AccessListsUsers::where('access_list_id', $list_id)
            ->where('account_id', Auth::$user->account_id)
            ->get()
            ->toArray();

        $acceptorsList = [];
        foreach ($acceptors as $acceptor) {
            $acceptorsList[$acceptor['user_id']] = $acceptor['user_id'];
        }

        return $acceptorsList;
    }

    static function _checkAccessOnCompany($listId, $companyId) {

        $contractors = self::_getContractors($listId);

        $access = false;
        foreach ($contractors as $contractor) {
            if ($contractor['id'] == $companyId) {
                $access = true;
                break;
            }
        }
        return $access;
    }

    static function _getContractors($listId) {

        $access = AccessListsUsers::where('access_list_id', $listId)
            ->where('user_id', Auth::$user->id)
            ->where('account_id', Auth::$user->account_id)
            ->with('contractors.contractor')
            ->get()
            ->first();

        if (!$access) {
            return [];
        } else {
            $contractors = [];
            foreach ($access['contractors'] as $contractor) {
                $contractors[] = $contractor['contractor'];
            }
            return $contractors;
        }

    }
}

?>
