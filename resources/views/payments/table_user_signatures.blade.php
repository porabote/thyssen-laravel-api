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
        padding: 6px;
    }
    .pspTable td {
        padding: 8px 10px;
        border: 0;
        font-size: 12px;
    }
    .pspTable p {
        padding: 0px;
        line-height: 7px;
    }
</style>
<!-- Таблица.. -->
<table class="pspTable" style="width: 300px;">

    <tr style="padding: 10px;">
        <td colspan="2" style="border-right: 2px solid #0060df; font-weight: 900;" class="table_list__item">
            <p>Rechnung anerkannt</p>
            <p>{{$status_name}}</p>
            <p>{{$user['post_name']}}</p>
        </td>
    </tr>

    <tr>
        <td height="70" style="border-bottom: 2px solid #0060df;margin-left: 8px;"></td>
        <td valign="bottom" style="border-right: 2px solid #0060df;font-weight: 900;width: 140px;" class="table_list__item">
            <p>{{$user['name']}}</p>
            <p>{{$user['fio_en']}}</p>
        </td>
    </tr>

    <tr>
        <td height="30" style="border-bottom: 2px solid #0060df;"></td>
        <td valign="middle" style="border-right: 2px solid #0060df; border-bottom: 2px solid #0060df; font-size: 14px;font-weight: 900;width: 140px;" class="table_list__item">
            <p>{{$date_accept}}</p>
        </td>
    </tr>

</table>
<!-- ..Таблица -->
