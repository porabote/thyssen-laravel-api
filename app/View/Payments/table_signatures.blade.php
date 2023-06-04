<html>
<body>
<h1>Hello, {{ $name }}</h1>
</body>
</html>
{{--<?--}}
{{--$this->setLayout(false);--}}

{{--$info = json_decode($payment['data_json'], true);--}}
{{--//debug($info);--}}
{{--?>--}}
{{--<style>--}}
{{--    @page {--}}
{{--        margin: 0px;--}}
{{--    }--}}
{{--    .pspTable {--}}
{{--        font-size: 12px;--}}
{{--        color: #0050b7;--}}
{{--        border-collapse: collapse;--}}
{{--        border-top: 2px solid #0060df;--}}
{{--        border-left: 2px solid #0060df;--}}
{{--        max-width: 600px;--}}
{{--    }--}}
{{--    .pspTable td {--}}
{{--        padding: 4px 10px;--}}
{{--        border-right: 1px solid #0060df;--}}
{{--        border-bottom: 1px solid #0060df;--}}
{{--        font-size: 12px;--}}
{{--    }--}}
{{--</style>--}}
{{--<!-- Таблица.. -->--}}

{{--<?--}}
{{--if(isset($info['info'])):--}}
{{--    $firstElement = $info['info'][array_key_first($info['info'])];--}}
{{--    ?>--}}
{{--<table class="pspTable"  cellpadding="0" cellspacing="0" width="350" style="width: 300px;">--}}

{{--    <tr class="table_list-head">--}}
{{--        <td colspan="3" style="text-align: center; border-right: 2px solid #0060df; border-bottom: 2px solid #0060df;font-weight: 900;" class="table_list__item">--}}
{{--            Buchungskreis <?=$firstElement['object_name']?>--}}
{{--        </td>--}}
{{--    </tr>--}}

{{--    <tr class="table_list-head">--}}
{{--        <td style=" font-weight: 900;" class="table_list__item">Betrag</td>--}}
{{--        <td style=" font-weight: 900;" class="table_list__item">LV-Position</td>--}}
{{--        <td style=" border-right: 2px solid #0060df; border-bottom: 1px solid #0060df;font-weight: 900;" class="table_list__item">PSP-Element</td>--}}
{{--    </tr>--}}
{{--        <?foreach($info['info'] as $item):?>--}}
{{--    <tr class="table_list-tr">--}}
{{--        <td style=" " class="table_list__item"><?=$item['summa']?></td>--}}
{{--        <td style=" " class="table_list__item"><?=$item['location']?></td>--}}
{{--        <td style=" border-right: 2px solid #0060df; border-bottom: 1px solid #0060df;" class="table_list__item"><?=$item['psp']?></td>--}}
{{--    </tr>--}}

{{--    <?endforeach;?>--}}

{{--</table>--}}
{{--<?endif;?>--}}
{{--        <!-- ..Таблица -->--}}
