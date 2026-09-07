<h2>Звіт перевірки доменів</h2>

<table border="1" cellpadding="5" cellspacing="0">
    <tr>
        <th>Домен</th>
        <th>Статус</th>
        <th>Код</th>
        <th>Час</th>
        <th>Помилка</th>
    </tr>

    @foreach($checks as $check)
        <tr>
            <td>{{ $check->domain->domain }}</td>
            <td>{{ $check->is_success ? 'Успіх' : 'Помилка' }}</td>
            <td>{{ $check->status_code }}</td>
            <td>{{ $check->response_time }}s</td>
            <td>{{ $check->error }}</td>
        </tr>
    @endforeach
</table>
