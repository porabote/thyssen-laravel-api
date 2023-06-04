<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Porabote\FullRestApi\Server\ApiTrait;
use Porabote\Uploader\Uploader;
use App\Models\File;
use App\Models\History;

class HistoryLocalController extends Controller
{
    use ApiTrait;
}
