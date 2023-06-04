<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\PurchaseRequest;
use App\Models\File;
use Porabote\FullRestApi\Server\ApiTrait;
use Porabote\Uploader\Uploader;
use App\Models\History;
use App\Models\HistoryLocal;
use App\Models\Comment;
use App\Models\Config;
use App\Models\ApiUsers;
use App\Http\Components\Mailer\Mailer;
use App\Http\Components\Mailer\Message;
use App\Http\Controllers\ObserversController;
use App\Http\Controllers\AcceptListsController;
use Porabote\Auth\Auth;
use App\Http\Components\AccessLists;
use App\Http\Controllers\PurchaseNomenclaturesController;

class PurchaseRequestController extends Controller
{
    use ApiTrait;

    static $authAllows;
    private $authData = [];

    function __construct()
    {
        self::$authAllows = [
            'getWidjetData',
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
        $message->setData($msgData)->setTemplateById(7);

        ObserversController::_subscribe([Auth::$user->id], [10], $data['record_id']);

        Mailer::setToByEventId([10], $data['record_id']);
        Mailer::send($message);

        return response()->json([
            'data' => $data,
            'meta' => []
        ]);
    }

    function getById($id)
    {
        $record = PurchaseRequest::find($id);
        $data = $record->getAttributes();
        $data['user'] = $record->user->getAttributes();
        $data['status'] = $record->status->getAttributes();
        $data['initator'] = ($record->initator) ? $record->initator->getAttributes() : [];
        $data['object'] = ($record->object) ? $record->object->getAttributes() : [];

        return $data;
    }

    /*
     * ACCEPT LIST
     * */
    function getAcceptListMode($request)
    {
        $data = $request->all();
        $record = PurchaseRequest::with('steps.acceptor')->find($data['foreignKey'])->toArray();

        $mode = (in_array($record['status_id'], ['1', '2']))  ? 'building' : 'signing';
        $isCanChangeAcceptor = AccessLists::_check(10);


        $isCanAccept = false;
        $nextSignerId = AcceptListsController::getNextSigner($record['steps']);
        if ($nextSignerId) $isCanAccept = AcceptListsController::_checkIsCanAccept($nextSignerId);

        return response()->json([
            'data' => [
                'mode' => $mode,
                'isCanChangeAcceptor' => $isCanChangeAcceptor,
                'isCanAccept' => $isCanAccept,
            ],
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
        $record = PurchaseRequest::with('steps')->find($id);

        $isAllAccepted = true;
        $acceptor = null;
        foreach($record['steps'] as $step) {
            if (!$step['acceptor']['accepted_at']) {
                $isAllAccepted = false;
                $acceptor = $step['acceptor'];
                break;
            }
        }

        if ($isAllAccepted) {
            $record->status_id = 13;
            $record->who_sign_queue_id = null;
            $record->update();

            PurchaseNomenclaturesController::_isChangeRequestStatus($record->id);

            return $this->notifyAboutAccepting($id);
        } else {
            $record->status_id = 3;
            $record->who_sign_queue_id = $acceptor['user_id'];
            $record->update();

            PurchaseNomenclaturesController::_isChangeRequestStatus($record->id);

            HistoryLocal::create([
                'model_alias' => 'PurchaseRequest',
                'record_id' => $record->id,
                'label' => 'acceptListSaved',
                'msg' => 'Подпись лист сформирован и сохранён.'
            ]);

            return $this->notifyNextSigner($id);
        }
    }

    function declineStepCallback($id, $data)
    {
        $record = PurchaseRequest::find($id);
        $record->status_id = 2;
        $record->who_sign_queue_id = null;
        $record->update();

        PurchaseNomenclaturesController::_isChangeRequestStatus($record->id);

        HistoryLocal::create([
            'model_alias' => 'PurchaseRequest',
            'record_id' => $record->id,
            'msg' => 'Подпись отклонена. Причина: ' . $data['comment']
        ]);

        $this->notifyAboutDeclining($id, $data);
    }

    function notifyNextSigner($id)
    {
        $data = PurchaseRequest::with('steps.acceptor.api_user')
            ->with('object')
            ->with('initator')
            ->with('status')
            ->with('user')
            ->find($id)
            ->toArray();
        $data['record'] = $data;

        $nextSigner = null;
        foreach ($data['steps'] as $step) {
            if (!$step['acceptor']['accepted_at']) {
                $nextSigner = $step['acceptor']['api_user'];
                break;
            }
        }

        if(!$nextSigner) return;
        $data['nextSigner'] = $nextSigner;

        $message = new Message();
        $message
            ->setData($data)
            ->setTemplateById(12);

        Mailer::setTo([
            [$nextSigner['email']]
        ]);
        Mailer::send($message);
    }

    function notifyAboutAccepting($id)
    {
        $data = PurchaseRequest::with('steps.acceptor.api_user')
            ->with('user')
            ->with('object')
            ->with('initator')
            ->with('status')
            ->find($id)
            ->toArray();
        $data['record'] = $data;

        $recipients = [];
        foreach ($data['steps'] as $step) {
            if (!$step['acceptor']['accepted_at']) {
                $recipients[] = [$step['acceptor']['api_user']['email']];
            }
        }

        $message = new Message();
        $message
            ->setData($data)
            ->setTemplateById(13);

        Mailer::setTo($recipients);
        Mailer::send($message);
    }

    function notifyAboutDeclining($id, $dataRequest)
    {
        $data = PurchaseRequest::with('steps.acceptor.api_user')
            ->with('object')
            ->with('initator')
            ->with('status')
            ->with('user')
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
            ->setTemplateById(14);

        Mailer::setTo($recipients);
        Mailer::send($message);
    }

    function changeAcceptorCallback($id, $data)
    {
        $record = PurchaseRequest::with('steps.acceptor.api_user')
            ->find($id);

        $isAllAccepted = true;
        $acceptor = null;
        foreach($record['steps'] as $step) {
            if (!$step['acceptor']['accepted_at']) {
                $isAllAccepted = false;
                $acceptor = $step['acceptor'];
                break;
            }
        }
        if (!$isAllAccepted) {
            $record->department_now_id = $acceptor['api_user']['department_id'];
            $record->who_sign_queue_id = $acceptor['user_id'];
            $record->update();
        }

        HistoryLocal::create([
            'model_alias' => 'PurchaseRequest',
            'record_id' => $id,
            'msg' => 'Изменён подписант с ' . $data['oldStep']['acceptor']['api_user']['name'] . ' на ' .$data['newStep']['acceptor']['api_user']['name']
        ]);

        $this->notifyNextSigner($id);
    }

    function getWidjetData()
    {
        $users = AccessLists::_get(3);

        $users = ApiUsers::whereIn('id', $users)
           // ->with('Avatar')
            ->get()
            ->toArray();

        $requests = PurchaseRequest::where('status_id', 4)
            ->with('nomenclatures.manager')
            ->with('nomenclatures.status')
            ->with('steps.acceptor')
            ->get()
            ->toArray();

        return response()->json([
            'data' => [
                'users' => $users,
                'requests' => $requests
            ],
            'meta' => []
        ]);

    }

}
