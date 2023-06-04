<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Porabote\FullRestApi\Server\ApiTrait;
use App\Http\Middleware\Auth;
use App\Models\Comments;

class CommentsController extends Controller
{
    use ApiTrait;


    function create() {
        $data = request()->input();
        $comment = Comments::create($data);

        return response()->json([
            "data" => $data,
        ]);
    }
}
