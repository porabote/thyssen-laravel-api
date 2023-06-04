<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Porabote\FullRestApi\Server\ApiTrait;
use App\Exceptions\ApiException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use App\Http\Components\Thyssen\CredInform\CredInform;
use App\Models\Companies;
use App\Models\Entrepreneurs;
use App\Models\Contractors;

class ContractorsCredinformController extends Controller
{
    use ApiTrait;

    public $ctypes;
    static $authAllows;

    function __construct()
    {
        self::$authAllows = [
            'get',
        ];

        $this->ctypes = [
            'Индивидуальный предприниматель' => 'ИП',
            'Общество с ограниченной ответственностью' => 'ООО',
            'Непубличное акционерное общество' => 'ЗАО',
            'Публичное акционерное общество' => 'ПАО',
            'Индивидуальное частное предприятие' => 'ИЧП',
            'Федеральное государственное бюджетное учреждение' => 'ГБУ',
            'Представительство' => 'Представительство',
            'Филиал' => 'Филиал',
            'Акционерное общество' => 'АО',
            'Обособленное подразделение' => 'ОП',
            'Закрытое акционерное общество' => 'ЗАО',
            'Производственный кооператив (кроме сельскохозяйственного производственного кооператива)' => 'ПК',
            'Структурное подразделение обособленного подразделения' => 'СПОП',
            'Ассоциация (союз)' => 'Ассоциация',
            'Федеральное государственное казенное учреждение' => 'ФГКУ',
            'Государственное бюджетное учреждение субъекта Российской Федерации' => 'ГБУСРФ',
            'Автономная некоммерческая организация' => 'АНО',
            'Частное учреждение' => 'ЧО',
            'Унитарное предприятие' => 'Унитарное предприятие',
            'Адвокатское бюро' => 'АБ',
            'Открытое акционерное общество' => 'ОАО',
            'Коллегия адвокатов' => 'КА',
            'Муниципальное автономное учреждение' => 'МАУ',
            'Иные некоммерческие организации, не включенные в другие группировки' => '',
            'Федеральное государственное унитарное предприятие' => 'ФГУП',
            'Фонд' => 'Фонд',
            'Государственное казенное учреждение субъекта Российской Федерации' => 'ГКУСРК',
            'Государственное предприятие' => 'ГП',
            'Муниципальное бюджетное учреждение' => 'МБУ',
            'Государственное автономное учреждение субъекта Российской Федерации' => 'ГАУСРФ',
            'Муниципальное унитарное предприятие' => 'МУП'
        ];
    }

    public function get(Request $request)
    {
        $inn = request()->input('inn');

        $data = CredInform::get($inn);

        return response()->json([
            'data' => isset($data['companyDataList']) ? $data['companyDataList'] : [],
            'meta' => []
        ]);
    }

    public function save()
    {
        $input = request()->input();
        $data = $input['records'][0]['attributes'];

        $inn = $data['taxNumber'];
        $innLength = strlen($inn);

        $data = [
            'name' => $data['companyName'],
            'inn' => $data['taxNumber'],
            'okpo' => $data['statisticalNumber'],
            'ogrn' => (isset($data['registrationNumber'])) ? $data['registrationNumber'] : null,
            'kpp' => (isset($data['taxRegistrationReasonCode'])) ? $data['taxRegistrationReasonCode'] : null,
            'credinform_guid' => $data['companyId'],
            'full_name' => $this->ctypes[$data['legalForm']] . ' «' . $data['companyName'] . "»",
        ];

        try {
            if ($innLength == 10) {
                $company = Companies::where('inn', $data['inn'])->get()->first();
                if ($company) {
                    throw new \App\Exceptions\ApiException("Компания с таким ИНН уже существует");
                }

                Companies::create($data);

                $data['model'] = 'Companies';
                $newRecord = Contractors::create($data);
            } else if ($innLength == 12) {
                $entrepreneur = Entrepreneurs::where('inn', $data['inn'])->get()->first();
                if ($entrepreneur) {
                    throw new \App\Exceptions\ApiException("ИП с таким ИНН уже существует");
                }
                Entrepreneurs::create($data);

                $data['model'] = 'Entrepreneurs';
                $newRecord = Contractors::create($data);
            }
        } catch (\App\Exceptions\ApiException $e) {
            return $e->toJSON();
        }

        return response()->json([
            'data' => $newRecord->toArray(),
            'meta' => []
        ]);
    }

}
