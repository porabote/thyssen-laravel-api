<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Bills;
use App\Models\File;
use Porabote\FullRestApi\Server\ApiTrait;
use Porabote\Uploader\Uploader;
use App\Models\History;
use App\Models\Comment;
use App\Models\Config;
use App\Http\Components\Mailer\Mailer;
use App\Http\Components\Mailer\Message;
use App\Http\Controllers\ObserversController;
use Porabote\Auth\Auth;
use App\Http\Components\AccessLists;
use App\Models\HistoryLocal;

class BillsController extends Controller
{
    use ApiTrait;

    function addComment(Request $request)
    {
        $data = $request->all();

        Comment::create($data);

        $msgData = $data;
        $msgData['record'] = $this->getById($data['record_id']);
        $msgData['comment'] = $data;

        $message = new Message();
        $message->setData($msgData)->setTemplateById(8);

        ObserversController::_subscribe([Auth::$user->id], [12], $data['record_id']);

        Mailer::setToByEventId([12], $data['record_id']);
        Mailer::send($message);

        return response()->json([
            'data' => $data,
            'meta' => []
        ]);
    }

    function getById($id)
    {
        $record = Bills::find($id);
        $data = $record->getAttributes();
        $data['user'] = $record->user->getAttributes();
        $data['status'] = $record->status->getAttributes();
        $data['object'] = $record->object->getAttributes();

        return $data;
    }

    /*
     * ACCEPT LIST
     * */
    function getAcceptListMode($request)
    {
        $data = $request->all();
        $record = Bills::find($data['foreignKey']);

        $mode = (in_array($record->status_id, ['23', '26']))  ? 'building' : 'signing';
        $isCanChangeAcceptor = AccessLists::_check(10);

        return response()->json([
            'data' => [
                'mode' => $mode,
                'isCanChangeAcceptor' => $isCanChangeAcceptor,
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
        $record = Bills::with('steps')->find($id);

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
            $record->status_id = 25;
            $record->who_sign_queue_id = null;
            $record->update();
            return $this->notifyAboutAccepting($id);
        } else {
            $record->status_id = 24;
            $record->who_sign_queue_id = $acceptor['user_id'];
            $record->update();
            return $this->notifyNextSigner($id);
        }
    }

    function declineStepCallback($id, $data)
    {
        $record = Bills::find($id);
        $record->status_id = 26;
        $record->who_sign_queue_id = null;
        $record->update();

        HistoryLocal::create([
            'model_alias' => 'Bills',
            'record_id' => $record->id,
            'msg' => 'Подпись отклонена. Причина: ' . $data['comment']
        ]);

        $this->notifyAboutDeclining($id, $data);
    }

    function notifyNextSigner($id)
    {
        $data = Bills::with('steps.acceptor.api_user')
            ->with('user')
            ->with('object')
            ->with('contractor')
            ->with('client')
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
            ->setTemplateById(10);

        Mailer::setTo([
            [$nextSigner['email']]
        ]);
        Mailer::send($message);
    }

    function notifyAboutAccepting($id)
    {
        $data = Bills::with('steps.acceptor.api_user')
            ->with('user')
            ->with('object')
            ->with('contractor')
            ->with('client')
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
            ->setTemplateById(11);

        Mailer::setTo($recipients);
        Mailer::send($message);
    }

    function notifyAboutDeclining($id, $dataRequest)
    {
        $data = Bills::with('steps.acceptor.api_user')
            ->with('user')
            ->with('object')
            ->with('contractor')
            ->with('client')
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
            ->setTemplateById(17);

        Mailer::setTo($recipients);
        Mailer::send($message);
    }

    function changeAcceptorCallback($id, $data)
    {
        $record = Bills::with('steps.acceptor.api_user')
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
            'model_alias' => 'Bills',
            'record_id' => $id,
            'msg' => 'Изменён подписант с ' . $data['oldStep']['acceptor']['api_user']['name'] . ' на ' .$data['newStep']['acceptor']['api_user']['name']
        ]);

        $this->notifyNextSigner($id);
    }
    /*
     * ACCEPT LIST
     * */

}
