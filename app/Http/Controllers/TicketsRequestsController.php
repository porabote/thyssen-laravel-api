<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\TicketsRequests;
use App\Models\Tickets;
use App\Models\File;
use App\Models\History;
use App\Models\Comment;
use Porabote\FullRestApi\Server\ApiTrait;
use Porabote\Uploader\Uploader;
use App\Http\Controllers\AcceptListsController as AcceptLists;
use App\Http\Components\Mailer\Mailer;
use App\Http\Components\Mailer\Message;
use Porabote\Components\Excel\Excel;
use Porabote\Auth\Auth;
use Porabote\Stringer\Stringer;

class TicketsRequestsController extends Controller
{
    use ApiTrait;

    static $authAllows;

    function __construct()
    {
        self::$authAllows = [
            'create',
            'downloadTickets',
        ];
    }

    function addComment(Request $request)
    {
        $data = $request->all();

        Comment::create($data);

        $msgData = $data;
        $msgData['record'] = $this->getById($data['record_id']);
        $msgData['comment'] = $data;

        $message = new Message();
        $message->setData($msgData)->setTemplateById(22);

        ObserversController::_subscribe([Auth::$user->id], [20], $data['record_id']);

        Mailer::setToByEventId([20], $data['record_id']);
        Mailer::send($message);

        return response()->json([
            'data' => $data,
            'meta' => []
        ]);
    }

    function getById($id)
    {
        $record = TicketsRequests::find($id);
        $data = $record->getAttributes();
        $data['user'] = $record->user->getAttributes();
        $data['status'] = $record->status->getAttributes();

        return $data;
    }

    function create(Request $request)
    {
        $data = $request->all();

        if (!isset($data['id'])) {
            $record = TicketsRequests::create($data);
            AcceptLists::_addStepsByDefault(4, $record->id, 'Tickets');

        } else {
            $record = TicketsRequests::find($data['id']);
            $record->update();
        }

        return response()->json([
            'data' => $record,
            'meta' => []
        ]);
    }

    function getTickets($request, $id)
    {
        $tickets = Tickets::where('ticket_request_id', $id)
            ->get();

        return response()->json([
            'data' => $tickets,
            'meta' => []
        ]);
    }

    /*
     * ACCEPT LIST
     * */
    function getAcceptListMode($request)
    {
        $data = $request->all();
        $record = TicketsRequests::find($data['foreignKey']);

        $mode = ($record->status_id == 73)  ? 'building' : 'signing';

        return response()->json([
            'data' => ['mode' => $mode],
            'meta' => []
        ]);
    }

    function acceptListEventsCallback($request)
    {
        $data = $request->all();

        switch ($data['action']) {
            case 'setAcceptors': $this->setAcceptorsCallback($data['foreignKey']); break;
            case 'acceptStep': $this->setAcceptorsCallback($data['foreignKey']); break;
            case 'declineStep': $this->declineStepCallback($data['foreignKey'], $data); break;
            case 'changeAcceptor': $this->changeAcceptorCallback($data['foreignKey'], $data); break;
        }

        return response()->json([
            'data' => $data,
            'meta' => []
        ]);
    }

    function setAcceptorsCallback($id)
    {
        $record = TicketsRequests::with('steps')->find($id);

        $isAllAccepted = true;
        foreach($record['steps'] as $step) {
            if (!$step['acceptor']['accepted_at']) {
                $isAllAccepted = false;
                break;
            }
        }

        if (!$isAllAccepted) {
            $record->status_id = 74;
            $record->update();
            return $this->notifyNextSigner($id);
        } else {
            $record->status_id = 75;
            $record->update();
            return $this->notifyAboutAccepting($id);
        }
    }

    function declineStepCallback($id, $data)
    {
        $record = TicketsRequests::find($id);
        $record->status_id = 77;
        $record->update();

        History::create([
            'model_alias' => 'TicketsRequests',
            'record_id' => $record->id,
            'msg' => 'Подпись отклонена. Причина: ' . $data['comment']
        ]);

        $this->notifyAboutDeclining($id, $data);
    }
    
    function notifyNextSigner($id)
    {
        $data = TicketsRequests::with('steps.acceptor.api_user')
            ->with('user')
            ->find($id)
            ->toArray();
        $data['record'] = $data;
        $nextSignerEmail = null;

        foreach ($data['steps'] as $step) {
            if (!$step['acceptor']['accepted_at']) {
                $nextSignerEmail = $step['acceptor']['api_user']['email'];
                break;
            }
        }

        if(!$nextSignerEmail) return;

        $message = new Message();
        $message->setData($data)->setTemplateById(24);

        Mailer::setTo([
            [$nextSignerEmail]
        ]);
        Mailer::send($message);
    }

    function notifyAboutAccepting($id)
    {
        $data = TicketsRequests::with('steps.acceptor.api_user')
            ->with('user')
            ->find($id)
            ->toArray();
        $data['record'] = $data;

        $recipients = [];
        foreach ($data['steps'] as $step) {
            $recipients[] = [$step['acceptor']['api_user']['email']];
        }

        $message = new Message();
        $message
            ->setData($data)
            ->setTemplateById(23);

        Mailer::setTo($recipients);
        Mailer::send($message);
    }

    function notifyAboutDeclining($id, $dataRequest)
    {
        $data = TicketsRequests::with('steps.acceptor.api_user')
            ->with('user')
//            ->with('object')
//            ->with('contractor')
//            ->with('client')
            ->find($id)
            ->toArray();
        $data['record'] = $data;
        $data['comment'] = $dataRequest['comment'];
        $data['acceptor_name'] = Auth::$user->name;

        $recipients = [];
        foreach ($data['steps'] as $step) {
            if (!$step['acceptor']['accepted_at']) {
                $recipients[] = [$step['acceptor']['api_user']['email']];
            }
        }

        $message = new Message();
        $message
            ->setData($data)
            ->setTemplateById(25);

        Mailer::setTo($recipients);
        Mailer::send($message);
    }

    function downloadTickets($request)
    {
        $data = $request->all();
        $ticketRequest = TicketsRequests::with('tickets.user.passport')
            ->with('tickets.user.passport_foreign')
            ->with('tickets.city_from')
            ->with('tickets.city_to')
            ->find($data['request_id'])
            ->toArray();

        $excel = new Excel(config('paths.base_path') . '/storage/export/tickets/tickets_request.xlsx');
        $list = $excel->getActiveSheet();


        $rowStyle = [
            'font' => [
                //'bold' => true,
            ],
            'borders' => [
                'left' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM,
                ],
                'right' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM,
                ],
                'bottom' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                ],
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'color' => [
                    'argb' => 'F2F3F3',
                ],
            ],
        ];

        $lastRowStyle = [
            'font' => [
                'bold' => false,
            ],
            'borders' => [
                'left' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM,
                ],
                'right' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM,
                ],
                'bottom' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM,
                ],
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'color' => [
                    'argb' => 'F2F3F3',
                ],
            ],
        ];

        $list->setCellValue('D4', str_pad($ticketRequest['id'], 4, 0, STR_PAD_LEFT));
        $list->setCellValue('D5', $ticketRequest['comment']);

        $row = 10;
        foreach ($ticketRequest['tickets'] as $ticket) {
            
            $name_en = '';
            if ($ticket['user']['passport_foreign']) {
                $name_en = $ticket['user']['passport_foreign']['name_en'] . ' ' . $ticket['user']['passport_foreign']['last_name_en'];
            }

            if (empty($name_en) && !empty($ticket['user']['passport'])) {
                $name_en = ucfirst(Stringer::transcript($ticket['user']['passport']['last_name'])) . ' ' . ucfirst(Stringer::transcript($ticket['user']['passport']['name']));
            }
            
            $list->setCellValue('C' . $row, "{$name_en}");//{$ticket['user']['name']} {$ticket['user']['patronymic']}
            $list->setCellValue('D' . $row, "{$ticket['date']}");
            $list->setCellValue('E' . $row, "{$ticket['city_from']['name_en']}");
            $list->setCellValue('F' . $row, "{$ticket['city_to']['name_en']}");

            $sery = '';
            $number = '';
            $passport_type = '';
            if ($ticket['passport_type'] == 'russian' && $ticket['user']['passport']) {
                $sery = $ticket['user']['passport']['sery'];
                $number = $ticket['user']['passport']['number'];
                $passport_type = 'Passport';
            } else if ($ticket['user']['passport_foreign']) {
                $sery = $ticket['user']['passport_foreign']['sery'];
                $number = $ticket['user']['passport_foreign']['number'];
                $passport_type = 'Passport foreign';
            }

            $list->setCellValue('G' . $row, "{$sery}/{$number}");//{$passport_type}
            $list->setCellValue('H' . $row, $ticket['comment']);

            $list->getStyle('B' . $row . ':H' . $row)->applyFromArray($rowStyle);
         //   $list->getStyle('B' . $row . ':H' . $row)->()->setIndent(1);

            $row++;
        }

        $list->getStyle('B' . $row . ':H' . $row . '')->applyFromArray($rowStyle);
        $list->getStyle('B' . $row . ':H' . $row)
            ->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_NONE);
        $row++;

        $list->setCellValue('H' . $row, ' ');
        $list->getStyle('B' . $row . ':H' . $row . '')->applyFromArray($rowStyle);
        $list->getStyle('B' . $row . ':H' . $row)
            ->getBorders()->getTop()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM);
        $list->getStyle('B' . $row . ':H' . $row)
            ->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_NONE);
        $row++;

        $list->setCellValue('C' . $row, 'Anzahl der Passagiere');
        $list->setCellValue('D' . $row, count($ticketRequest['tickets']));
        $list->getStyle('B' . $row . ':H' . $row)->applyFromArray($rowStyle);
        $list->getStyle('B' . $row . ':H' . $row)->getAlignment()->setWrapText(true);
//        $list->getStyle('B' . $row . ':H' . $row)
//            ->getBorders()->getTop()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        $list->getStyle('B' . $row . ':H' . $row)
            ->getBorders()->getBottom()->setBorderStyle(0);
//        $list->getStyle('B' . $row . ':H' . $row)
//            ->getBorders()->getTop()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM);

        $list->getStyle('C' . $row)->getFont()->getColor()->setARGB('7C7B7B');
        $list->getStyle('C' . $row)->getFont()->setSize(12);
        $list->getStyle('D' . $row)->getFont()->setSize(14);

        $row++;
        $list->setCellValue('C' . $row, 'Кол-во пассажиров');
        $list->getStyle('C' . $row)->getFont()->setSize(9);
        $list->getStyle('C' . $row)->getFont()->getColor()->setARGB('7C7B7B');
        $list->getStyle('B' . $row . ':H' . $row)->applyFromArray($rowStyle);
        $list->getStyle('B' . $row . ':H' . $row)
            ->getBorders()->getBottom()->setBorderStyle(0);

        $row++;
        $list->getStyle('B' . $row . ':H' . $row)->applyFromArray($lastRowStyle);

        $excel->output('php://output', 'Ticketbuchung');
    }

    function edit($request)
    {
        $data = $request->all();

        $record = TicketsRequests::find($data['id']);

        foreach ($data as $field => $value) {
            if (array_key_exists($field, $record->getAttributes())) $record->$field = $value;
        }

        $record->update();

        return response()->json([
            'data' => $record,
            'meta' => []
        ]);
    }
    
    function setStatusAsBought($request)
    {
        $data = $request->all();

        $record = TicketsRequests::with('user')->find($data['id']);
        $record->status_id = 76;
        $record->update();

        $msgData['record'] = $record->toArray();

        $message = new Message();
        $message->setData($msgData)->setTemplateById(27);

        Mailer::setTo($record['user']['email']);
        Mailer::send($message);

        History::create([
            'model_alias' => 'TicketsRequests',
            'record_id' => $record['id'],
            'msg' => 'Заявка исполнена.',
        ]);
        
        return response()->json([
            'data' => $record,
            'meta' => []
        ]);
        
    }
}