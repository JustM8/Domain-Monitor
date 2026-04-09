<h2>Domain Check Report</h2>

<table border="1" cellpadding="5" cellspacing="0">
    <tr>
        <th>Domain</th>
        <th>Status</th>
        <th>Code</th>
        <th>Time</th>
        <th>Error</th>
    </tr>

    @foreach($checks as $check)
        <tr>
            <td>{{ $check->domain->domain }}</td>
            <td>{{ $check->is_success ? 'OK' : 'FAIL' }}</td>
            <td>{{ $check->status_code }}</td>
            <td>{{ $check->response_time }}s</td>
            <td>{{ $check->error }}</td>
        </tr>
    @endforeach
</table>
