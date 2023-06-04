<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Tickets;
use App\Models\TicketsRequests;
use App\Models\File;
use Porabote\FullRestApi\Server\ApiTrait;
use Porabote\Uploader\Uploader;
use App\Http\Controllers\AcceptListsController as AcceptLists;
use App\Http\Components\Mailer\Mailer;
use App\Http\Components\Mailer\Message;

class TicketsController extends Controller
{
    use ApiTrait;

    function addTickets($request)
    {
        $data = $request->all();

        $request = TicketsRequests::find($data['request_id'])->toArray();

        foreach ($data['users'] as $user) {
            Tickets::create(
                [
                    'date' => $request['date'],
                    'city_from_id' => $user['city_id'],
                    'city_to_id' => $request['city_to_id'],
                    'ticket_request_id' => $data['request_id'],
                    'user_id' => $user['id'],
                    'passport_type' => $request['passport_type'],
                ]
            );
        }
     
        return response()->json([
            'data' => $data,
            'meta' => []
        ]);
    }

    function edit($request)
    {
        $data = $request->all();

        $record = Tickets::find($data['id']);

        foreach ($data as $field => $value) {
            if (array_key_exists($field, $record->getAttributes())) $record->$field = $value;
        }

        $record->update();

        return response()->json([
            'data' => $record,
            'meta' => []
        ]);
    }

    function delete($request)
    {
        $data = $request->all();
        $record = Tickets::find($data['id']);
        $record->delete();

        return response()->json([
            'data' => [],
            'meta' => []
        ]);
    }
}