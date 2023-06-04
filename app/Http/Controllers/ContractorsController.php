<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Porabote\FullRestApi\Server\ApiTrait;
use App\Exceptions\ApiException;
use App\Http\Components\Thyssen\CredInform\CredInform;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use App\Models\Contractors;
use Porabote\Stringer\Stringer;

class ContractorsController extends Controller
{
    use ApiTrait;

    static $authAllows;

    function __construct()
    {
        self::$authAllows = [
            'getFileByCredInform',
            'getFinancialEconomicIndicators',
            'downloadPdf'
        ];
    }

    function getFinancialEconomicIndicators($request)
    {
        try {
            if (!$request->query('taxNumber') || !$request->query('statisticalNumber')) {
                throw new ApiException('TaxNumber or statisticalNumber is empty');
            }


            $info = CredInform::getCompanyInfo($request->query('taxNumber'), $request->query('statisticalNumber'));

            $shema = '{
              "period": {            
                "from": "2010-01-01T00:00:00"            
              },           
              "companyId": "' . $info['companyId'] . '",            
              "language": "Russian"          
            }';

            $data = CredInform::getBlobData($shema, '/api/CompanyInformation/FinancialEconomicIndicators?apiVersion=1.5');


            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $sheet->setCellValue('A1', 'Показатель');
            $sheet->setCellValue('B1', 'Дата');
            $sheet->setCellValue('C1', 'Значение');
            $sheet->setCellValue('D1', 'Валюта');

            $row = 2;


            foreach ($data->data->financialIndicatorValueList as $datum) {

                $sheet->setCellValue('A' . $row, $datum->financialIndicatorDescription);
                $row++;

                foreach ($datum->financialIndicatorValueByTypeList as $val) {

                    $sheet->setCellValue('B' . $row, $val->date);
                    $sheet->setCellValue('C' . $row, $val->value);
                    $sheet->setCellValue('D' . $row, $val->currency->currencyCode);
                    $row++;
                }
            }


            header("Content-type:application/octet-stream");
            header("Accept-Ranges:bytes");
            header("Content-type:application/vnd.ms-excel");
            header("Content-Disposition:attachment;filename=financial.xlsx");

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');

            exit();

        } catch (ApiException $e) {
            $e->jsonApiError();
        }
    }

    function getFileByCredInform($request)
    {
        try {
            if (!$request->query('taxNumber') || !$request->query('statisticalNumber')) {
                throw new ApiException('TaxNumber or statisticalNumber is empty');
            }


            $info = CredInform::getCompanyInfo($request->query('taxNumber'), $request->query('statisticalNumber'));

            $shema = '{
                "language": "Russian",
                "companyId": "' . $info['companyId'] . '",
                "sectionKeyList": [
                "ContactData", 
                "RegData",
                "FirstPageFinData",
                "Rating",
                "CompanyName",
                "UserVerification",
                "Bankruptcy",
                "LeadingFNS",	        	        	         
                "ShareFNS",	
                "Subs_short",	
                "SRO",
                "Pledges",	
                "ArbitrageInNewFormat",
                "EnforcementProceeding"
              ]
            }';
            $blobData = CredInform::getBlobData($shema);

            $shema = '{
              "period": {            
                "from": "2010-01-01T00:00:00"            
              },           
              "companyId": "948d514c-b993-40cf-b7d4-f1e7ad9c473c",            
              "language": "Russian"          
            }';

            $this->exportToExcel($blobData, $info);

        } catch (ApiException $e) {
            $e->jsonApiError();
        }
    }

    function exportToExcel($blobData, $info)
    {
        $fileName = \Porabote\Stringer\Stringer::transcript($info['captionName']);
//debug($blobData);
        $fileData = base64_decode($blobData->file->fileContents);

        $temp = tmpfile();
        fwrite($temp, $fileData);
        fseek($temp, 0);

        header("Content-type:application/pdf");
        header("Content-Disposition:attachment;filename=$fileName.pdf");

        echo readfile(stream_get_meta_data($temp)['uri']);
        fclose($temp);
    }

    public function downloadPdf($request, $id)
    {
        $guid = $request->input('guid');

        $user = new \stdClass();
        $user->account_alias = $request->account_alias;
        \Porabote\Auth\Auth::setUser($user);


        if (!$guid && $id) {
            $contractor = Contractors::find($id);
            $fileName = Stringer::transcript($contractor['name']);
            $companyFile = CredInform::getFilePdf($contractor->credinform_guid);
        } else {
            $companyFile = CredInform::getFilePdf($guid);
            $fileName = $guid;
        }

        $fileData = base64_decode($companyFile->file->fileContents);

        $filePath = storage_path() . '/credinform/';

        header("Content-type:application/pdf");
        header("Content-Disposition:attachment;filename=$fileName.pdf");

        echo $fileData;

    }

}
