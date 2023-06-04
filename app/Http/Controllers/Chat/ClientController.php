<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use Porabote\Auth\Auth;

class ClientController extends Controller
{

    function calculate(Request $request)
    {
        dd($request->all());
    }

}
