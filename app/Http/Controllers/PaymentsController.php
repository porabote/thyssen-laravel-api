<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use File as FileSystem;
use Porabote\FullRestApi\Server\ApiTrait;
use App\Models\PaymentsSets;
use App\Models\Payments;
use App\Models\Files;
use App\Models\File;
use App\Models\History;
use App\Models\HistoryLocal;
use App\Models\Comment;
use App\Models\Configs;
use App\Models\Dicts;
use App\Models\ApiUsers;
use App\Models\Bills;
use App\Http\Components\Mailer\Mailer;
use App\Http\Components\Mailer\Message;
use App\Http\Controllers\ObserversController;
use App\Http\Components\Thyssen\Schneider\Schneider;
use App\Http\Components\ImagesComponent;
use Porabote\Auth\Auth;
use Porabote\Components\ImageMagick\ImageMagickHandler;
use App\Http\Components\AccessLists;
use App\Http\Components\Thyssen\Payments\PspTableHandler;
use App\Http\Components\Thyssen\Payments\SignatureTableHandler;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use App\Http\Components\Thyssen\Schneider\ExcelCreator;
use Carbon\Carbon;
use App\Exceptions\ApiException;

class PaymentsController extends Controller
{

    use ApiTrait;

    static $authAllows;
    private $authData = [];

    function __construct()
    {
        self::$authAllows = [
            'createFacsimileTableImage',
            'tmpReloadFiles',
        ];
    }

    // Company client guid => shneider folder alias
    private $system_aliases = [
        '000e40765a8-7e39-11dc-bd9a-000255dfb035' => 'dev',
        'e40765a8-7e39-11dc-bd9a-000255dfb035' => 'Norilsk',
        '786f130b-9be7-11db-8f46-0017314d44cc' => 'TMCE',
        '0545b3a8-1529-11e2-8dab-0050568f0010' => 'Solikamsk',
    ];

    function requestForCancelPayment($request, $id)
    {
        $data = $this->getPaymentData($id);

        if (!$data['accept_datetime']) {

            Schneider::connect();

            //$this->system_aliases[$data['client']['guid_schneider']] = 'dev';
            $filesList = Schneider::readFolder('/Thyssen24/' . $this->system_aliases[$data['client']['guid_schneider']] . '/xml_in/');

            foreach ($filesList as $fileName) {

                preg_match('/(payment_' . $id . ')/', $fileName, $matches);
                if ($matches) {

                    $content = Schneider::read($fileName);
                    Schneider::putToRemote(
                        str_replace('xml_in', 'xml_cancel', $fileName),
                        $content
                    );
                    $content = Schneider::deleteFile($fileName);
                    break;
                }
            }

            Schneider::disconnect();
        }

        // Меняем статус
        $payment = Payments::find($id);
        $payment->status_id = 55;
        $payment->save();

        // Отправляем письм
        $data['comment'] = $request->input('comment');
        $data['sender']['fio'] = Auth::$user->name;

        $message = new Message();
        $message
            ->setData($data)
            ->setTemplateById(5);

        Mailer::setToByDefault([7]);
        Mailer::send($message);

        //Пишем хистори
        History::create([
            'model_alias' => 'payments-sets',
            'record_id' => $data['payments_set_id'],
            'msg' => 'Платеж N ' . $data['id'] . ' повторно отправлен на оплату. Комментарий: ' . $data['comment']
        ]);

        return response()->json([
            'data' => $data,
            'meta' => []
        ]);
    }

    function cancelRequestApprove($request, $id)
    {
        // Меняем статус
        $payment = Payments::find($id);
        $payment->update(['status_id' => 58]);

        //Пишем хистори
        History::create([
            'model_alias' => 'payments',
            'record_id' => $id,
            'msg' => 'Платеж N ' . $id . ' был отменён после заявки на отмену. Платёж не был проведен. Комментарий: ' . $request->input('comment')
        ]);

        return response()->json([
            'data' => [],
            'meta' => []
        ]);
    }

    function cancelRequestDecline($request, $id)
    {
        // Меняем статус
        $payment = Payments::find($id);
        $payment->update(['status_id' => 56]);

        //Пишем хистори
        History::create([
            'model_alias' => 'payments',
            'record_id' => $id,
            'msg' => 'Платеж N ' . $id . ' не был отменён после заявки на отмену. Платёж отплачен. 
                Комментарий: ' . $request->input('comment')
        ]);

        return response()->json([
            'data' => [],
            'meta' => []
        ]);
    }

    function repeatPayment($request, $id)
    {
        $this->sendForPayment($id, 57);

        // Отправляем письмо
        $data = $this->getPaymentData($id);
        $data['comment'] = $request->input('comment');
        $data['sender']['fio'] = Auth::$user->name;

        $message = new Message();
        $message
            ->setData($data)
            ->setTemplateById(4);

        Mailer::setToByDefault([6]);
        Mailer::send($message);

        //Пишем хистори
        History::create([
            'model_alias' => 'payments-sets',
            'record_id' => $data['payments_set_id'],
            'msg' => 'Платеж N ' . $data['id'] . ' повторно отправлен на оплату. Комментарий: ' . $data['comment']
        ]);

        return response()->json([
            'data' => $data,
            'meta' => []
        ]);
    }

    function getPaymentData($id)
    {
        return Payments::with([
            'contractor',
            'client',
            'object',
            'bill'
        ])
            ->find($id)
            ->toArray();
    }

    function sendForPayment($id, $statusId = 50, $setId = null, $datePayment = null)
    {
        $paymentObj = Payments::with([
            'contractor',
            'client',
            'object',
            'bill',
            'payments_set',
        ])->find($id);

        $payment = $paymentObj->toArray();

        $pdfPath = $this->putScansToPdf($id, 'scan_for_scheider');

        $xml = $this->setPaymentXmlSchema($payment);
        //$this->system_aliases[$payment['client']['guid_schneider']] = 'dev';

        $fileName = '/Thyssen24/' . $this->system_aliases[$payment['client']['guid_schneider']] . '/xml_in/payment_' . $id . '__' . time();
        $pdfName = '/Thyssen24/' . $this->system_aliases[$payment['client']['guid_schneider']] . '/pdf_in/payment_' . $id . '__' . time();

        Schneider::connect();

        $success = Schneider::putToRemote($fileName . '.xml', $xml);

        if ($pdfPath) {
            $success = Schneider::putToRemote($pdfName . '.pdf', file_get_contents($pdfPath), 'FTP_BINARY');
        }

        Schneider::disconnect();

        if ($success) {
            $paymentObj->status_id = $statusId;
            if ($setId) {
                $paymentObj->payments_set_id = $setId;
                $paymentObj->date_payment = $datePayment;
            }
            $paymentObj->save($payment);
        }

    }

    public function putScansToPdf($id, $outputName = null)
    {

        $imagesList = [];

        $lists = $this->_getScanFiles($id, false);

        ImageMagickHandler::$tmpPath = storage_path() . '/ImageMagick/tmp';

        foreach ($lists as $list) {

            $newImagePath = ImageMagickHandler::cloneToTmp($list['path']);
            foreach ($list['child'] as $elementOfImage) {

                $x = 0;
                $y = 0;

                if ($elementOfImage['data_json']) {

                    $axis = json_decode($elementOfImage['data_json'], true);

                    $x = $axis['axis']['top'];
                    $y = $axis['axis']['left'];
                }

                ImageMagickHandler::composite($newImagePath, $elementOfImage['path'], $x, $y);

            }

            $imagesList[] = $newImagePath;

        }
        ob_clean();

        $Mpdf = new \Mpdf\Mpdf;
        foreach ($imagesList as $list) {
            $Mpdf->WriteHTML('<img src="' . $list . '">');
        }

        if (!$outputName) {
            $Mpdf->Output($id . 'payments__scans.pdf', \Mpdf\Output\Destination::INLINE);
            exit();
        } else {
            if ($imagesList) {
                $path = ImageMagickHandler::$tmpPath . '/' . time() . '__' . $outputName . '.pdf';
                $Mpdf->Output($path, \Mpdf\Output\Destination::FILE);
                return $path;
            } else {
                return null;
            }
        }

    }

    function getScanFiles($request, $id)
    {
        $scansFiles = $this->_getScanFiles($id);

        return response()->json([
            'data' => $scansFiles,
            'meta' => []
        ]);
    }

    /*
 *  Get/Set Scans
 *
 * */
    function _getScanFiles($id)
    {

        $payment = Payments::find($id);

        $paymentScans = [];

        if (!$payment->bill_file_id) {
            //$this->refreshScanFiles($payment->id);
        } else {
            // Если есть сканы, находим их
            $paymentScans = File::where('flag', 'on')
                ->where('record_id', $payment->id)
                ->where('model_alias', 'payments')
                ->where('label', 'imgFromPdf')
                ->where('account_id', Auth::$user->account_id)
                ->where('parent_id', $payment->bill_file_id)
                ->get()
                ->toArray();

            //Печати и подписи
            foreach ($paymentScans as &$scan) {
                $scan['child'] =
                    File::where('parent_id', $scan['id'])
                        ->whereIn('label', ['paymentSighTable', 'signInTable'])
                        ->where('flag', 'on')
                        ->where('account_id', Auth::$user->account_id)
                        ->get()
                        ->toArray();
            }

            return $paymentScans;
        }
    }


    /**
     * Opens the current file with a given $mode
     *
     * @param string $paymentId integer - payment ID
     * @return void
     */
    public function setPaymentXmlSchema($payment)
    {
        $payments_types = \App\Models\PaymentsTypes::get()->pluck('name', 'value')->toArray();

        Schneider::connect();
        $simpleXml = Schneider::readFile('/Thyssen24/patterns/payment.xml');

        $simpleXml->Payment->DatePayment = date('d.m.Y', strtotime($payment['date_payment']));
        $simpleXml->Payment->PaymentGUID = $payment['guid'];
        $simpleXml->Payment->PaymentThyssenId = $payment['id'];

        // Получатель платежа
        $simpleXml->Payment->Contractor->FullName = $payment['contractor']['name'];
        $simpleXml->Payment->Contractor->INN = $payment['contractor']['inn'];
        $simpleXml->Payment->Contractor->KPP = $payment['contractor']['kpp'];
        $simpleXml->Payment->Contractor->GUID = "";//$payment['contractor']['guid_schneider'];

        // Плательщик (Тиссен)
        $simpleXml->Payment->Client->FullName = $payment['client']['name'];
        $simpleXml->Payment->Client->INN = $payment['client']['inn'];
        $simpleXml->Payment->Client->KPP = $payment['client']['kpp'];
        $simpleXml->Payment->Client->Project = $payment['object']['schneider_name'];
        $simpleXml->Payment->Client->GUID = $payment['client']['guid_schneider'];

        // Счёт (документ)
        $simpleXml->Payment->Bill->Number = $payment['bill']['number'];
        $simpleXml->Payment->Bill->Date = date('d.m.Y h:i:s', strtotime($payment['bill']['date']));

        // Данные платежа
        $simpleXml->Payment->Bill->CurrencyBill = str_replace('RUR', 'руб.', $payment['bill']['currency']);
        $simpleXml->Payment->Bill->CurrencyPayment = 'руб.';

        $summa = $payment['summa'];
        $nds_summa = $payment['nds_summa'];
        if ($payment['bill']['currency'] == 'EUR') {
            $summa = $payment['summa'] * $payment['payments_set']['rate_euro'];
            $nds_summa = $payment['nds_summa'] * $payment['payments_set']['rate_euro'];
        } else if ($payment['bill']['currency'] == 'USD') {
            $summa = $payment['summa'] * $payment['payments_set']['rate_usd'];
            $nds_summa = $payment['nds_summa'] * $payment['payments_set']['rate_usd'];
        }

        $simpleXml->Payment->Summa = number_format(round($summa, 2), 2, '.', '');
        $simpleXml->Payment->NdsPercent = ($payment['nds_percent']) ? $payment['nds_percent'] : 'Без НДС';
        $simpleXml->Payment->NdsSumma = number_format(round($nds_summa, 2), 2, '.', '');

        $simpleXml->Payment->PurposeOfPayment = mb_strtolower($payment['purpose']);
        $simpleXml->Payment->PercentOfPayment = $payment['percent_of_bill'];
//echo $payment['client']['guid_schneider'];
        if ($payment['client']['guid_schneider'] != '786f130b-9be7-11db-8f46-0017314d44cc') {
            $simpleXml->Payment->VoCode = '((VO' . $payment['vo_code'] . '))';
        }
        $simpleXml->Payment->VoType = mb_strtolower($payments_types[$payment['pay_type']]);

        $simpleXml->ThyssenAccount = Auth::$user->account_alias;

        Schneider::disconnect();

        return $simpleXml->asXML();

    }

    function getById($id)
    {
        $set = Payments::find($id);
        $data = $set->getAttributes();
        $data['bill'] = $set->bill->getAttributes();
        return $data;
    }

    function setAccept($request, $id)
    {
        $record = Payments::find($id);

        if (!AccessLists::_checkAccessOnCompany(14, $record->client_id)) {
            return response()->json(['error' => 'Извините, у вас нет разрешений для акцептования платежей.']);
        }

        if (!in_array($record->status_id, [41, 42])) {
            return response()->json(['error' => 'Платёж нельзя акцептовать в данном статусе.']);
        }

        if ($request->input('status')) {
            $record->status_id = 42;
            $record->acceptor_id = Auth::$user->id;
            $record->accept_datetime = \Carbon\Carbon::now();
        } else {
            $record->status_id = 41;
            $record->acceptor_id = null;
            $record->accept_datetime = null;
        }
        $record->update();

        $statusName = $record->status_id == 41 ? '«Не акцептован»' : '«Акцептован»';
        HistoryLocal::create([
            'model_alias' => 'payments',
            'record_id' => $record->id,
            'msg' => 'Изменил статус на <b>' . $statusName . '</b>'
        ]);

        return response()->json([
            'data' => $record->toArray(),
            'meta' => []
        ]);
    }


    public function createImageOfPspTable()
    {
        try {

            $fileId = request()->input("scanFileId");
            $paymentId = request()->input("paymentId");

            // рендерим HTML таблицу
            $pspHtmlTable = PspTableHandler::renderHtmlTable($paymentId);

            // получаем данные о файле счета
            $file = File::find($fileId)->toArray();

            // Creating Folder
            $date = new \DateTime();
            $pdfPath = storage_path() . '/payments/psps/';
            FileSystem::makeDirectory($pdfPath, 755, true, true);
            $pdfPath .= $date->getTimestamp() . '.pdf';

            // Init Handler
            PspTableHandler::htmlToPdf($pspHtmlTable, [
                'path' => $pdfPath,
                'pageSize' => [
                    'width' => 70
                ]
            ]);

            $imgsList = PspTableHandler::pdfToImg([
                'path' => $pdfPath,
                'ext' => 'png',
                'record_id' => $paymentId,
                'parent_id' => $file['id'],
                'label' => 'paymentSighTable',
                'pageSize' => [
                    'width' => 400,
                    'height' => ''
                ]
            ]);
            unlink($pdfPath);

            $imgMetaData = $imgsList[0];
            ImagesComponent::setOpacity($imgMetaData['path']);
            ImagesComponent::setBackground($imgsList[0]['path'], $imgMetaData);

            //помечаем на удаление старые файлы
            $files = File::where("parent_id", $file['id'])->where("label", "paymentSighTable")->get();
            foreach ($files as $file) {
                $file->flag = "to_delete";
                $file->update();
            }

            File::create($imgMetaData);

            return response()->json([
                'data' => $files->toArray(),
                'meta' => []
            ]);

        } catch (\Error $error) {
            return response()->json([
                'error' => $error->getMessage(),
            ]);
        }

    }

    function createFacsimileTableImage()
    {
        try {

            $data = request()->input();

            $user = ApiUsers::find($data['user_id']);

            $facsimilePath = SignatureTableHandler::setCloneFacsimile($data['userId'], $data['paymentId']);

            // рендерим HTML таблицу
            $signatureHtmlTable = SignatureTableHandler::renderHtmlTable($user['id'], $data);

            $signTableImgPath = SignatureTableHandler::createPdfImages($data['paymentId'], $signatureHtmlTable, $data);

            //накладываем градиент на изображение
            exec('convert ' . $signTableImgPath . ' ' . $facsimilePath . ' -geometry +20+50  -composite  ' . $signTableImgPath . '');
            sleep(1);

            ImagesComponent::setOpacity($signTableImgPath);
            ImagesComponent::setBackground($signTableImgPath);

            //Помечаем старые файлы на удаление
            $filesForDelete = File::where('record_id', $data['paymentId'])
                ->where('parent_id', $data['scanId'])
                ->where('model_alias', 'Payments')
                ->where('label', 'signInTable')
                ->get();
            foreach ($filesForDelete as $deleteFile) {
                $deleteFile->flag = "to_delete";
                $deleteFile->update();
            }

            // Удаляем копию подписи после наложения на таблицу
            FileSystem::delete($facsimilePath);

            $facsimileFile = File::create([
                'path' => $signTableImgPath,
                'record_id' => $data['paymentId'],
                'parent_id' => $data['scanId'],
                'model_alias' => 'Payments',
                'label' => 'signInTable',
            ]);

            return response()->json([
                'data' => $facsimileFile->toArray(),
                'meta' => []
            ]);

        } catch (\App\Exceptions\ApiException $error) {
            $error->toJSON();
        }
    }

    function checkButtonAccess()
    {

        return response()->json([
            'data' => [
                'isCanAccept' => AccessLists::_check(14),
                'contractors' => AccessLists::_getContractors(14),
            ],
            'meta' => []
        ]);
    }

    public function downloadScans()
    {
        ImageMagickHandler::$tmpPath = storage_path() . '/ImageMagick/tmp';

        $imagesList = [];

        $paysIds = request()->input('ids');

        foreach ($paysIds as $id) {

            $filesRecords = $this->_getScanFilesNew($id);

            foreach ($filesRecords as $list) {

                $newImagePath = ImageMagickHandler::cloneToTmp($list['path']);

                foreach ($list['child'] as $elementOfImage) {

                    $x = 0;
                    $y = 0;
                    if ($elementOfImage['data_json']) {
                        $axis = json_decode($elementOfImage['data_json'], true);
                        $x = $axis['axis']['top'];
                        $y = $axis['axis']['left'];
                    }

                    ImageMagickHandler::composite($newImagePath, $elementOfImage['path'], $x, $y);
                }
                $imagesList[] = $newImagePath;
            }

        }

        // ob_clean();

        $Mpdf = new \Mpdf\Mpdf;
        foreach ($imagesList as $img) {
            $Mpdf->WriteHTML('<img src="' . $img . '">');
        }

        $tmpPath = storage_path() . '/tmp/scan_copies_' . date('Y-m-d_H:i:s') . '.pdf';
        $Mpdf->Output($tmpPath, \Mpdf\Output\Destination::FILE);

        $file = File::create([
            'path' => $tmpPath,
            'flag' => 'to_delete',
            'model_alias' => 'payments',
        ]);

        return response()->json([
            'data' => $file,
            'meta' => []
        ]);
    }


    function _getScanFilesNew($id)
    {

        $payment = Payments::find($id);

        $paymentScans = [];

        if (!$payment->bill_file_id) {
            //$this->refreshScanFiles($payment->id);
        } else {
            // Если есть сканы, находим их
            $paymentScans = File::where('flag', 'on')
                ->where('record_id', $payment->id)
                ->where('model_alias', 'payments')
                ->where('label', 'imgFromPdf')
                ->where('parent_id', $payment->bill_file_id)
                ->get()
                ->toArray();

            //Печати и подписи
            foreach ($paymentScans as &$scan) {
                $scan['child'] =
                    File::where('parent_id', $scan['id'])
                        ->whereIn('label', ['paymentSighTable', 'signInTable'])
                        ->where('flag', 'on')
                        ->where('account_id', Auth::$user->account_id)
                        ->get()
                        ->toArray();
            }

            return $paymentScans;
        }
    }


    function tmpReloadFiles()
    {

        $user = new \stdClass();
        $user->account_alias = 'Solikamsk';//Thyssen   Solikamsk
//        $user->account_id = 3;
//        $user->id = 0;
        \Porabote\Auth\Auth::setUser($user);

//        $payments = HistoryLocal::where('model_alias', 'App.Payments')->get();
//foreach($payments as $payment) {
//    $payment->model_alias = 'payments';
//    $payment->update();
//}

//        $filesScans = File::where('flag', 'on')
//            ->where('label', 'imgFromPdf')
//            ->get()
//        ->toArray();
//
//
//        foreach ($filesScans as $fileScan) {
//            $files = File::where('flag', 'on')
//                ->where('record_id', $fileScan['record_id'])
//                ->whereIn('label', ['signInTable', 'paymentSighTable'])//App.Payments
//                ->where('flag', 'on')
//                ->get();
//
//            foreach ($files as $fileDetail) {
//                $fileDetail->parent_id = $fileScan['id'];
//                $fileDetail->update();
//            }
//        }


        // Если есть сканы, находим их
//        $filesNew = File::limit(10000)
//            ->where('label', 'paymentSighTable')//App.Payments
////            ->where('flag', 'on')
////            ->whereNull('exported_at')
//            ->get();
//         //  debug($filesNew);
//
//           foreach ($filesNew as $file) {echo $file->id . '|';
//               $f = $file::find($file->id);
//               $f->delete();
//           }
//           exit();

        // Если есть сканы, находим их
//        $files = Files::limit(3000)
//            ->where('model_alias', 'Files')//App.Payments
//            ->where('flag', 'on')
//            ->whereNull('exported_at')
//            ->get();

        //       foreach ($files as $file) {

//            $file->exported_at = \Carbon\Carbon::now();
//            $file->update();
//
//
//            $path = storage_path() .
//                str_replace(
//                    'var/www/www-root/data/www/thyssen24.ru/webroot/upload_files/files',
//                    'payments/imported',
//                    $file['path']);
//
//            FileSystem::makeDirectory(pathinfo($path)['dirname'], 755, true, true);
//            echo($file->id);
//            copy($file->path, $path);
//
//            File::create([
//                'path' => $path,
//                'record_id' => $file->record_id,
//                'parent_id' => $file->parent_id,
//                'model_alias' => 'payments',
//                'label' => $file->label,
//                'main' => $file->main,
//                'data_s_path' => $file->data_s_path,
//                'data_json' => $file->data_json,
//                'user_id' => $file->user_id,
//            ]);
        //   }
    }


    function downloadXlsx()
    {
        try {

            $data = request()->input();
            $set = $this->setPaymentsSet($data);

            // Генерируем XLSX файл на сервере
            $excel = new ExcelCreator();

            $filePath = $excel->create($set);

            return response()->json([
                'data' => [
                    'uri' => '/files/' . str_replace(config('paths.storage_path'), '', $filePath),
                ],
                'meta' => []
            ]);
        }  catch (ApiException $error) {
            $error->toJSON();
        }
    }

    function setPaymentsSet($data)
    {
        $setData = [];
        $setData['id'] = 0;
        $setData['rate_euro'] = floatval(str_replace(',', '.', $data['rate_eur']));
        $setData['rate_usd'] = floatval(str_replace(',', '.', $data['rate_usd']));
        $setData['summa_eur'] = 0;
        $setData['date_payment'] = Carbon::create($data['date'])->format('d.m.Y');
        $setData['week'] = Carbon::create($data['date'])->weekOfYear;
        $setData['sender_id'] = Auth::$user->id;
        $setData['payments'] = (count($data['payments_ids']) == 0) ? [0] : Payments::whereIn('id', $data['payments_ids'])
            ->with(['bill', 'contractor', 'object'])
            ->get()
            ->toArray();

        return $setData;
    }


    public function updateScanFiles()
    {
        $data = request()->input();

        //помечаем на удаление старые файлы
        $currentFiles = File::where('record_id', $data['recordId'])
            ->where('model_alias', 'payments')
            ->whereIn('label', ['imgFromPdf', 'signInTable', 'paymentSighTable'])
            ->get();
        foreach ($currentFiles as $currentFile) {
            $currentFile->flag = 'to_delete';
            $currentFile->update();
        }


        // Set PdfFile ID for Payments
        $payment = Payments::find($data['recordId']);
        $payment->bill_file_id = $data['billFileId'];
        $payment->update();

        return $this->refreshScanFiles($payment, $data['billFileId']);

    }

    function refreshScanFiles($payment, $billFileId)
    {
        $billFile = Files::find($billFileId)->toArray();

        if ($billFile) {
            $paymentExtractedScans = PspTableHandler::pdfToImg([
                'path' => $billFile['path'],
                'ext' => 'jpg',
                'record_id' => $payment->id,
                'parent_id' => $billFileId,
                'model_alias' => 'payments',
            ]);

//            return response()->json([
//                'data' => $paymentExtractedScans,
//                'meta' => []
//            ]);

            $paymentScans = [];
            foreach ($paymentExtractedScans as $file) {

                $newPath = storage_path() . '/payments/scans/' . $file['basename'];
                rename($file['path'], $newPath);

                $file['path'] = $newPath;
                $file['uri'] = '/files/' . str_replace(config('paths.storage_path'), '', $file['path']);
                $paymentScans[] = File::create($file);
            }

            return response()->json([
                'data' => $paymentScans,
                'meta' => []
            ]);

            // $payment->bill_file_id = $billFile->id;
            //$this->Payments->save($payment);

        }
    }


//    function extractionImagesFromPdf($billFile, $record_id)
//    {
//
//        $imgsList = PspTableHandler::pdfToImg([
//            'path' => $billFile->path,
//            'ext' => 'jpg',
//            'record_id' => $record_id,
//            'parent_id' => $billFile['id'],
//            'model_alias' => 'payments',
////            'pageSize' => [
////                'width' => 400,
////                'height' => ''
////            ]
//        ]);
//       // unlink($pdfPath);
//
////        $imgsList = $pdfHandler->pdfToImg([
////            'path' => $billFile->path,
////            'imgFormat' => 'jpg',
////            'record_id' => $record_id,
////            'parent_id' => $billFile['id'],
////            'model_alias' => 'App.Payments'
////        ]);
//
//
//        return $imgsList;
//
//    }


    public function getScansAsPdf()
    {

        $id = request()->input('payment_id');
        debug($id);
        $scansShareList = [];

        $lists = $this->_getScanFiles($id, false);

        ImageMagickHandler::$tmpPath = storage_path() . '/ImageMagick/tmp';

        foreach ($lists as $list) {

            $newImagePath = ImageMagickHandler::cloneToTmp($list['path']);
            foreach ($list['child'] as $elementOfImage) {

                $x = 0;
                $y = 0;

                if ($elementOfImage['data_json']) {

                    $axis = json_decode($elementOfImage['data_json'], true);

                    $x = $axis['axis']['top'];
                    $y = $axis['axis']['left'];
                }

                ImageMagickHandler::composite($newImagePath, $elementOfImage['path'], $x, $y);

            }

            $scansShareList[] = $newImagePath;

        }
        ob_clean();

        $Mpdf = new \Mpdf\Mpdf;
        foreach ($scansShareList as $list) {
            $Mpdf->WriteHTML('<img src="' . $list . '">');
        }

        $tmpPath = storage_path() . '/tmp/scan_copies_' . date('Y-m-d_H:i:s') . '.pdf';
        $Mpdf->Output($tmpPath, \Mpdf\Output\Destination::FILE);

        $file = File::create([
            'path' => $tmpPath,
            'flag' => 'to_delete',
            'model_alias' => 'payments',
        ]);

        return response()->json([
            'data' => $file,
            'meta' => []
        ]);

    }

    public static function updateAfterBillUpdated($request, $billId)
    {
        $bill = Bills::with('payments')->find($billId);

        foreach ($bill->payments as $payment) {
            $payment->object_id = $bill->object_id;
            $payment->update();
        }

    }

}
