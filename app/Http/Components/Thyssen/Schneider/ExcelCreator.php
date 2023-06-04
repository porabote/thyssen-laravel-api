<?php
namespace App\Http\Components\Thyssen\Schneider;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use App\Exceptions\ApiException;

class ExcelCreator {

    private $alphabet;
    private $row = 3;
    private $column = 0;
    private $excelPattern;
    private $excel;
    private $setData;

    function __construct()
    {
        foreach (range('A', 'Z') as $char) {
            $this->alphabet[] = $char;
        }
    }

    function setAlphabet()
    {
        foreach (range('A', 'Z') as $char) {
            $this->alphabet[] = $char;
        }
    }

    function create($setData)
    {

            $setData['amount_rur_total'] = 0;
            $setData['amount_eur_total'] = 0;
            $this->setData = $setData;

            $patternPath = config('paths.storage_path') . '/export/payments-sets/zahlungs-plan.xlsx';
            $fileName = 'zahlungs-plan_N_' . $setData['id'] . '_' . date('Y-m-d') . '.xlsx';
            $filePath = config('paths.storage_path') . '/upload/payments-sets/';

            $filePath .= $fileName;

            $this->excelPattern = \PhpOffice\PhpSpreadsheet\IOFactory::load($patternPath);

            $this->excel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

            $this->excel->getProperties()
                ->setCreator('Porabote');

            $this->setCellsWidth($this->excel);

            $objects = [];
            foreach ($setData['payments'] as $payment) {

                $objects[$payment['object_id']]['id'] = $payment['object']['id'];
                $objects[$payment['object_id']]['name'] = $payment['object']['name'];
                $payment['data_json'] = json_decode($payment['data_json'], true);
                $payment['psps'] = '';
                if ($payment['data_json']) {
                    foreach ($payment['data_json']['info'] as $info) {
                        if (isset($info['psp'])) {
                            $payment['psps'] .= $info['psp'] . '; ';
                        }
                    }
                }

                $objects[$payment['object_id']]['payments'][] = $payment;
            }

            $style = $this->excelPattern->getActiveSheet()->getStyle('A2');
            $this->excel->getActiveSheet()->duplicateStyle($style, 'B1:D1');

            $this->excel->getActiveSheet()->setCellValue('B1', 'Zahlungsplan');
            $this->excel->getActiveSheet()->setCellValue('C1', $setData['week'] . ' Week');

            if (is_string($setData['date_payment'])) {
                $date = Carbon::create($setData['date_payment'])->format('d.m.Y');
            } else {
                $date = $setData['date_payment']->format('d.m.Y');
            }
            $this->excel->getActiveSheet()->setCellValue('D1', 'Stand ' . $date);


            foreach ($objects as $data) {
                $this->addTableDataToExcel($data);
            }

            $writer = new Xlsx($this->excel);
            $writer->save($filePath);

            return $filePath;

    }

    function setCellsWidth()
    {
        $this->excel->getActiveSheet()->getColumnDimension('A')->setWidth(5);
        $this->excel->getActiveSheet()->getColumnDimension('B')->setWidth(35);
        $this->excel->getActiveSheet()->getColumnDimension('C')->setWidth(15);
        $this->excel->getActiveSheet()->getColumnDimension('D')->setWidth(13);
        $this->excel->getActiveSheet()->getColumnDimension('E')->setWidth(60);
        $this->excel->getActiveSheet()->getColumnDimension('F')->setWidth(17);
        $this->excel->getActiveSheet()->getColumnDimension('G')->setWidth(16);
        $this->excel->getActiveSheet()->getColumnDimension('H')->setWidth(16);
        $this->excel->getActiveSheet()->getColumnDimension('I')->setWidth(35);
        $this->excel->getActiveSheet()->getColumnDimension('J')->setWidth(15);
        $this->excel->getActiveSheet()->getColumnDimension('K')->setWidth(35);
        $this->excel->getActiveSheet()->getColumnDimension('L')->setWidth(25);
    }

    function addTableDataToExcel($data)
    {
        $column = 0;

        $this->setTableHead($data);

        $this->setData['amount_rur'] = 0;
        $this->setData['amount_eur'] = 0;
        foreach ($data['payments'] as $payment) {
            $this->writeRecordToExcel($payment);
        }

        $this->setAmountRow();
        $this->setTableFooter();
    }

    function setTableHead($data)
    {
        $this->cloneExcelRow('A1:L1', $this->alphabet[$this->column] . $this->row);
        $this->excel->getActiveSheet()->setCellValue('E' . $this->row, $data['name']);
        $this->row++;

        $this->cloneExcelRow('A2:L2', $this->alphabet[$this->column] . $this->row);
        $this->row++;
        $this->cloneExcelRow('A3:L3', $this->alphabet[$this->column] . $this->row);
        $this->row++;

    }

    function cloneExcelRow($patternAxis, $documentAxis)
    {

        $values = $this->excelPattern->getActiveSheet()->rangeToArray($patternAxis);
        $style = $this->excelPattern->getActiveSheet()->getStyle($patternAxis);

        $this->excel->getActiveSheet()->duplicateStyle($style, $documentAxis . ':L' . $this->row);
        $this->excel->getActiveSheet()->fromArray($values, NULL, $documentAxis);


    }

    function writeRecordToExcel($payment)
    {
        $this->column = 0;

        $style = $this->excelPattern->getActiveSheet()->getStyle('A4:L4');

        $this->excel->getActiveSheet()->duplicateStyle($style, $this->alphabet[$this->column] . $this->row . ':L' . $this->row);

        $this->excel->getActiveSheet()->setCellValue('A' . $this->row, $payment['id']);
        $this->excel->getActiveSheet()->setCellValue('B' . $this->row, $payment['contractor']['name']);
        $this->excel->getActiveSheet()->setCellValue('C' . $this->row, $payment['bill']['number']);
        $this->excel->getActiveSheet()->setCellValue('D' . $this->row, Carbon::create($payment['bill']['date'])->format('d.m.Y'));
        $this->excel->getActiveSheet()->setCellValue('E' . $this->row, $payment['bill']['comment']);

        $summaInRUR = $payment['summa'];
        switch ($payment['bill']['currency']) {
            case 'EUR':
                $summaInRUR = $payment['summa'] * $this->setData['rate_euro'];
                break;
            case 'USD':
                $summaInRUR = $payment['summa'] * $this->setData['rate_usd'];
                break;
        }

        if ($this->setData['rate_euro'] <= 0) {
            throw new ApiException('Пожалуйста, укажите курс EURO');
        }

        $summaInEUR = $summaInRUR / $this->setData['rate_euro'];
        $summaInEUR = round($summaInEUR, 2);

        $this->setData['amount_rur'] += $summaInRUR;
        $this->setData['amount_eur'] += $summaInEUR;

        $this->setData['amount_rur_total'] += $summaInRUR;
        $this->setData['amount_eur_total'] += $summaInEUR;

        $this->excel->getActiveSheet()->setCellValue('F' . $this->row, $summaInRUR);
        $this->excel->getActiveSheet()->setCellValue('G' . $this->row, $payment['summa'] . ' ' . $payment['currency']);
        $this->excel->getActiveSheet()->setCellValue('H' . $this->row, $summaInEUR);

        //$this->excel->getActiveSheet()->setCellValue('I' . $this->row, $payment['date_payment']->i18nFormat('dd.MM.yyyy'));


        $payTypes = ['avans' => 'Аванс', 'postoplata' => 'Оплата'];
        $this->excel->getActiveSheet()->setCellValue('I' . $this->row, $payment['comment']);
        $this->excel->getActiveSheet()->setCellValue('J' . $this->row, $payTypes[$payment['pay_type']]);
        $this->excel->getActiveSheet()->setCellValue('K' . $this->row, $payment['psps']);

        $this->row++;
    }

    function setAmountRow()
    {
        $this->excel->getActiveSheet()->setCellValue('F' . $this->row, number_format($this->setData['amount_rur'], 2, '.', ''));
        $this->excel->getActiveSheet()->setCellValue('H' . $this->row, number_format($this->setData['amount_eur'], 2, '.', ''));

        $this->row++;
    }

    function setTableFooter()
    {
        $this->row += 3;
    }
}
?>
