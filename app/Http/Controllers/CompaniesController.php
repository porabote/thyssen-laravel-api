<?php

namespace App\Http\Controllers;

use Porabote\FullRestApi\Server\ApiTrait;
use App\Http\Middleware\Auth;
use App\Models\Companies;

class CompaniesController extends Controller
{
    use ApiTrait;
}