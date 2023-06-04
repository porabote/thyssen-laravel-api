<style>
    @page {
        margin: 0px;
    }

    .pspTable {
        font-size: 12px;
        color: #0050b7;
        border-collapse: collapse;
        border-top: 2px solid #0060df;
        border-left: 2px solid #0060df;
        max-width: 600px;
    }

    .pspTable td {
        padding: 4px 10px;
        border-right: 1px solid #0060df;
        border-bottom: 1px solid #0060df;
        font-size: 12px;
    }
</style>
<!-- Таблица.. -->
<table class="pspTable" style="width: 300px;">

    <tr class="table_list-head">
        <td colspan="3"
            style="text-align: center; border-right: 2px solid #0060df; border-bottom: 2px solid #0060df;font-weight: 900;"
            class="table_list__item">
            Buchungskreis {{$object_name}}
        </td>
    </tr>

    <tr class="table_list-head">
        <td style="font-weight: 900;" class="table_list__item">Betrag</td>
        <td style="font-weight: 900;" class="table_list__item">LV-Position</td>
        <td style="border-right: 2px solid #0060df; border-bottom: 1px solid #0060df;font-weight: 900;"
            class="table_list__item">
            PSP-Element
        </td>
    </tr>

    @if (isset($data['info']))
        @foreach ($data['info'] as $item)
            <tr class="table_list-tr">
                <td class="table_list__item">{{$item['summa']}}</td>
                <td class="table_list__item">
                    @if(isset($item['location']))
                        {{$item['location']}}
                    @endif
                </td>
                <td style=" border-right: 2px solid #0060df; border-bottom: 1px solid #0060df;"
                    class="table_list__item">
                    @if(isset($item['psp']))
                        {{$item['psp']}}
                    @endif
                </td>
            </tr>
        @endforeach
    @endif

</table>
<!-- ..Таблица -->
