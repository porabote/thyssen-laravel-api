<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Porabote\FullRestApi\Server\ApiTrait;
use App\Models\PaymentsSets;
use App\Models\Bills;
use App\Models\Payments;
use App\Models\PaymentsRequests;
use App\Models\Files;
use App\Models\History;
use App\Models\Comment;
use App\Models\Configs;
use App\Models\Dicts;
use App\Http\Components\Mailer\Mailer;
use App\Http\Components\Mailer\Message;
use App\Http\Controllers\ObserversController;
use App\Http\Components\Thyssen\Schneider\Schneider;
use Porabote\Auth\Auth;
use Porabote\Components\ImageMagick\ImageMagickHandler;
use App\Exceptions\ApiException;
use App\Http\Controllers\FilesController;
use App\Models\File;

class PaymentsRequestsController extends Controller
{
    use ApiTrait;

    static $authAllows;
    private $authData = [];

    function __construct()
    {
        self::$authAllows = [
            'create',
            'sentNoticedAboutAdding',
        ];
    }

    function create($request)
    {
        try {

            $data = $request->all();

            if ($data['summa'] < 1) {
                throw new ApiException('Поле "Сумма" не заполнено');
            }

            $bill = Bills::with('contractor')->with('payment')->find($data['bill_id']);


            $summa = preg_replace('/([^\d\.]+)/', '', $data['summa']);
            $delta = $summa - $bill['summa'];

            // Проверка на загруженный файл счета (обязательно при измененной сумме)
            if ($summa != $bill['summa'] && !array_key_exists('files', $data)) {
                throw new ApiException('Сумма изменена. Пожалуйста, загрузите новую скан копию счёта');
            }

            // Создаём запрос на доплату
            $newRecord = [];
            $newRecord['bill_id'] = $bill['id'];
            $newRecord['post_id'] = Auth::$user->id;
            $newRecord['summa'] = $summa;
            $newRecord['comment'] = $data['comment'];
            $newRecord['delta'] = $delta;
            $newRecord['contractor_id'] = $bill['contractor_id'];
            $newRecord['status_id'] = 52;

            $newRecord = PaymentsRequests::create($newRecord);

            ObserversController::subscribeByDefaultList([18], $newRecord['id']);

            $files = array_key_exists('files', $data) ? $data['files'] : [];
            $this->uploadFiles($files, $newRecord);

            $this->sentNoticedAboutAdding($newRecord['id']);
            //notices

        } catch (ApiException $e) {
            $e->toJSON();
        }

        return response()->json([
            'data' => [
                'account_alias' => $newRecord->toArray(),
            ],
            'meta' => []
        ]);

    }

    function uploadFiles($files, $data)
    {
        $filesHandled = [];
        if (isset($files['bill'])) {

            $file = FilesController::uploadFile($files['bill'], [
                'record_id' => $data['id'],
                'model_alias' => 'App.BusinessRequests'
            ]);

            // Дублируем файл для счета
            $file['record_id'] = $data['bill_id'];
            $file['model_alias'] = 'Store.Bills';
            File::create($file);
        }
    }

    function sentNoticedAboutAdding($id)
    {
        $data = PaymentsRequests::with('contractor')
            ->with('bill')
            ->with('status')
            ->with('post')
            ->find($id)
            ->toArray();

        $message = new Message();
        $message
            ->setData($data)
            ->setTemplateById(28);

        $observers = ObserversController::_getByDefault(18);
        foreach ($observers as $observer) {
            Mailer::setTo([[$observer['email']]]);
            Mailer::send($message);
            Mailer::clearTo();
        }
    }

    function addComment(Request $request)
    {
        $businessEvent = 24;
        $data = $request->all();

        Comment::create($data);

        $msgData = $data;
        $msgData['record'] = $this->getById($data['record_id']);
        $msgData['comment'] = $data;

        $message = new Message();
        $message->setData($msgData)->setTemplateById(21);

        ObserversController::_subscribe([Auth::$user->id], [$businessEvent], $data['record_id']);

        Mailer::setToByEventId([$businessEvent], $data['record_id']);

        //Mailer::setTo('maksimov_den@mail.ru');
        Mailer::send($message);

        return response()->json([
            'data' => $data,
            'meta' => []
        ]);
    }

    function getById($id)
    {
        $record = PaymentsRequests::with('bill')->find($id);
        $data = $record->getAttributes();
        $data['user'] = $record->user->getAttributes();
        $data['bill'] = $record->bill->getAttributes();

        return $data;
    }

}
