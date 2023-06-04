<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Porabote\FullRestApi\Server\ApiTrait;
use App\Models\PaymentsSets;
use App\Models\Payments;
use App\Models\History;
use App\Models\Comment;
use App\Http\Components\Mailer\Mailer;
use App\Http\Components\Mailer\Message;
use App\Http\Controllers\ObserversController;
use App\Http\Components\Thyssen\Schneider\Schneider;
use App\Http\Components\Thyssen\Schneider\DataParser;
use App\Http\Components\Thyssen\Schneider\ExcelCreator;
use App\Http\Controllers\PaymentsController;
use App\Http\Components\AccessLists;
use Porabote\Auth\Auth;
use Carbon\Carbon;

class PaymentsSetsController extends Controller
{
    use ApiTrait;

    static $authAllows;
    private $authData = [];

    function __construct()
    {
        self::$authAllows = [
            'getCbrfCourses',
            'parseSchneiderGuids',
            'sendPayments',
            'sendNotification',
        ];
    }

    //https://api.thyssen24.ru/api/payments-sets/method/getPaymentsFeedbacks
    function getPaymentsFeedbacks()
    {
        Schneider::connect();

        $accountsFoldersMap = [
            'Thyssen' => [
                'Norilsk', 'TMCE'
            ],
            'Solikamsk' => [
                'Solikamsk', 'TMCE'
            ],
        ];

        foreach ($accountsFoldersMap[Auth::$user->account_alias] as $folderName) {

            $filesList = Schneider::readFolder('/Thyssen24/' . $folderName . '/xml_in_loaded');

            $paths = [
                'payment' => [],
                'contractor' => []
            ];
            foreach ($filesList as $filePath) {
                $filePrefix = explode('_', pathinfo($filePath)['filename'])[0];
                $paths[$filePrefix][] = $filePath;
            }
            //  debug($filesList);
            $handledPayments = $this->handlePaymentsList($paths['payment'], Auth::$user->account_alias);

        }
//        return response()->json([
//            'data' => $filesList,
//            'meta' => []
//        ]);
        Schneider::disconnect();

        return response()->json([
            'data' => $handledPayments,
            'meta' => []
        ]);
    }

    function handlePaymentsList($list, $accountAlias)
    {
        $i = 0;

        $handledPayments = [];

        foreach ($list as $path) {

            if ($i > 100) break;

            $paymentXml = Schneider::readFile($path);

            $id = (string)$paymentXml->Payment->PaymentThyssenId;
            $guid = (string)$paymentXml->Payment->PaymentGUID;
            $accept_datetime = (string)$paymentXml->Payment->Ftime;

            $systemAccount = (string)$paymentXml->ThyssenAccount;

            if ($accountAlias != $systemAccount) {
                continue;
            }

            $handledPayments[$id] = [
                'id' => $id,
                'guid' => $guid,
                'accept_datetime' => $accept_datetime,
                'account' => $systemAccount,
            ];

            $payment = Payments::find($id);

            if ($payment) {

                $payment->guid_schneider = $guid;
                $payment->accept_datetime = date('Y-m-d H:i:s', strtotime($accept_datetime));
                $payment->status_id = 56;
                $payment->save();

                Schneider::deleteFile($path);
            }


            $i++;
        }

        return $handledPayments;
    }

    function addComment(Request $request)
    {
        $data = $request->all();

        Comment::create($data);

        $msgData = $data;
        $msgData['payments-set'] = $this->getById($data['record_id']);

        $message = new Message();
        $message->setData($msgData)->setTemplateById(1);

        Mailer::setToByEventId([1], $data['record_id']);
        Mailer::send($message);

        return response()->json([
            'data' => $data,
            'meta' => []
        ]);
    }

    function getById($id)
    {
        $set = PaymentsSets::find($id);
        $data = $set->getAttributes();
        $data['bill'] = $set->bill->getAttributes();
        return $data;
    }

    /*
     * Получение курса ЦБ на дату
     * http://www.cbr.ru/development/sxml/
     * */
    function getCbrfCourses($request)
    {

        $data = [];

        if (request()->input('date_req')) {

            $data = [
                'url' => 'https://www.cbr.ru/scripts/XML_daily.asp',//?date_req=22/08/2020
                'uri' => urldecode(http_build_query([
                    'date_req' => request()->input('date_req')
                ]))
            ];
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $data['url'] . '?' . $data['uri'],
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_HEADER => 0, // allow return headers
                CURLOPT_COOKIESESSION => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => 0,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_POST => false
            ]);

            $output = curl_exec($ch);

            $simpleXml = \simplexml_load_string($output);
            curl_close($ch);

            $data = [];
            if (request()->input('currency_alias')) {

                foreach ($simpleXml->Valute as $record) {
                    if ($record->CharCode == request()->input('currency_alias')) {
                        $data = json_decode(json_encode($record), true);
                        break;
                    }
                };
            } else {
                $data = json_decode(json_encode($simpleXml), true);
            }
            $data['date'] = (string)$simpleXml->attributes()['Date'];
            $data['date_req'] = request()->input('date_req');
        }

        $filteredData = [
            "date" => $data["@attributes"]["Date"],
            "list" => [],
        ];
        foreach ($data["Valute"] as $val) {
            $filteredData["list"][$val["CharCode"]] = $val;
        }

        return response()->json([
            'data' => $filteredData,
            'meta' => []
        ]);
    }

    function parseSchneiderGuids()
    {
        $alias = request()->input("alias");
        DataParser::parseContractors($alias);
    }

    function create()
    {
        try {
            $requestData = request()->input();

            if (!AccessLists::_check(14)) {
                throw new \App\Exceptions\ApiException("У вас недостаточно прав для этого действия");
            }
            if (empty($requestData['payments_ids'])) {
                throw new \App\Exceptions\ApiException("Платежи не выбраны");
            }

            $payments = Payments::whereIn('id', $requestData['payments_ids'])
                ->with('bill')
                ->with('object')
                ->with('contractor')
                ->get()
                ->toArray();

            $set = $this->createPaymentsSet($requestData, $payments);

            $paymentsController = new PaymentsController();
            foreach ($payments as $payment) {
                $paymentsController->sendForPayment($payment['id'], 50, $set->id, $set->date_payment);
            }

            // Генерируем XLSX файл на сервере
            $set['payments'] = $payments;
            $excel = new ExcelCreator();
            $filePath = $excel->create($set->toArray());

            $this->sendNotification($set['id'], $filePath);

        } catch (\App\Exceptions\ApiException $e) {
            return $e->toJSON();
        }

        return response()->json([
            'data' => $set,
            'meta' => []
        ]);
    }

    function createPaymentsSet($data, $payments)
    {
        try {

            if (!$data['date']) {
                throw new \App\Exceptions\ApiException("Не указана дата");
            }
            if (!$data['rate_eur']) {
                throw new \App\Exceptions\ApiException("Пожалуйста, укажите курс EURO");
            }

            $newRecord['date_payment'] = $date = Carbon::create($data['date']);
            $newRecord['week'] = $newRecord['date_payment']->weekOfYear;
            $newRecord['rate_euro'] = floatval(str_replace(',', '.', $data['rate_eur']));
            $newRecord['rate_usd'] = floatval(str_replace(',', '.', $data['rate_usd']));
            $newRecord['payments_count'] = count($data['payments_ids']);

            $sumEur = 0;
            $sumRur = 0;
            foreach ($payments as $payment) {
                ;
                $sumEur += $payment['summa'] / $newRecord['rate_euro'];
                $sumRur += $payment['summa'];
            }
            $newRecord['summa_eur'] = $sumEur;
            $newRecord['summa_rur'] = $sumRur;

            return PaymentsSets::create($newRecord);
            //return PaymentsSets::find(549);

        } catch (\App\Exceptions\ApiException $e) {
            return $e->toJSON();
        }

    }


    function sendPayments()
    {

        $user = new \stdClass();
        $user->account_alias = 'Thyssen';//Thyssen   Solikamsk
        \Porabote\Auth\Auth::setUser($user);

        $schneider = new Schneider();
        $schneider::connect();


        $set = PaymentsSets::with([
            'payments.object',
            'payments.contractor',
            'payments.bill',
        ])->find(request()->input("setId"));

        foreach ($set['payments'] as $payment) {

            $Payment = new PaymentsController();

            $payment['date_payment'] = $set['date_payment'];

            $payment['payments_set_id'] = $set['id'];
            $payment['status_id'] = 50;
            $payment->update();

            // $Payment->sendForPayment($payment->id);
        }

        // Генерируем XLSX файл на сервере
        $excel = new ExcelCreator();
        $filePath = $excel->create($set->toArray());

        $this->sendNotification($set, $filePath);

        $schneider::disconnect();

        return response()->json([
            'data' => [],
            'meta' => []
        ]);
    }

    function sendNotification($setId, $filePath = null)
    {
//        $user = new \stdClass();
//        $user->account_alias = 'Thyssen';//Thyssen   Solikamsk
//        \Porabote\Auth\Auth::setUser($user);

        $setId = request()->input("setId") ? request()->input("setId") : $setId;

        $set = PaymentsSets::with([
            'user',
            'payments.object',
            'payments.contractor',
            'payments.client',
            'payments.bill',
            'payments.acceptor',
        ])->find($setId)->toArray();

        $set['acceptors'] = [];
        $set['objects'] = [];
        foreach ($set['payments'] as $payment) {
            $set['client'] = $payment['client'];
            $set['objects'][$payment['object']['name']] = $payment['object']['name'];
            $set['acceptors'][$payment['acceptor']['name']] = $payment['acceptor']['name'];
        }
        $set['objects'] = implode(';', $set['objects']);
        $set['acceptors'] = implode('; ', $set['acceptors']);

        if ($set['date_payment']) {
            $date = Carbon::create($set['date_payment']);
        } else {
            $date = Carbon::now();
        }
        $set['week'] = $date->format("W");
        $set['year'] = $date->format("Y");
        $set['created_at'] = Carbon::create($set['created_at'])->format("d/m/Y");

//        $set['amount_rur'] = number_format(round($set['amount_rur_total'], 2), 2, '.', '');
//        $set['amount_eur'] = number_format(round($set['amount_eur_total'], 2), 2, '.', '');

        $message = new Message();
        $message
            ->setData($set)
            ->setTemplateById(26)
            ->setAttachment('PaymentPlan.xlsx', $filePath);

        Mailer::setToByDefault([5]);
        Mailer::send($message);
    }


}
