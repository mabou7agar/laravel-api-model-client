<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Model Relations Debug Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .status-2xx { background-color: #d4edda; }
        .status-3xx { background-color: #fff3cd; }
        .status-4xx { background-color: #f8d7da; }
        .status-5xx { background-color: #f5c6cb; }
        .request-row { cursor: pointer; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>API Model Relations Debug Dashboard</h1>
            <div>
                <a href="{{ route('api-model-relations.debug.clear') }}" class="btn btn-danger" 
                   onclick="return confirm('Are you sure you want to clear all debug data?')">Clear Debug Data</a>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Total Requests</h5>
                        <p class="card-text display-4">{{ $stats['total_requests'] }}</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Success Rate</h5>
                        <p class="card-text display-4">{{ number_format($stats['success_rate'], 1) }}%</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Avg Response Time</h5>
                        <p class="card-text display-4">{{ number_format($stats['avg_response_time'] * 1000, 1) }} ms</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Endpoints</h5>
                        <p class="card-text display-4">{{ count($stats['endpoints']) }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        Status Codes
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Status Code</th>
                                    <th>Count</th>
                                    <th>Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($stats['status_codes'] as $code => $count)
                                <tr>
                                    <td>{{ $code }}</td>
                                    <td>{{ $count }}</td>
                                    <td>{{ number_format(($count / $stats['total_requests']) * 100, 1) }}%</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        Top Endpoints
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Endpoint</th>
                                    <th>Count</th>
                                    <th>Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($stats['endpoints'] as $endpoint => $count)
                                <tr>
                                    <td>{{ $endpoint }}</td>
                                    <td>{{ $count }}</td>
                                    <td>{{ number_format(($count / $stats['total_requests']) * 100, 1) }}%</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <h2>Recent API Requests</h2>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Method</th>
                        <th>Endpoint</th>
                        <th>Status</th>
                        <th>Duration</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $request)
                        @php
                            $statusClass = 'status-2xx';
                            if ($request['status_code'] >= 300 && $request['status_code'] < 400) {
                                $statusClass = 'status-3xx';
                            } elseif ($request['status_code'] >= 400 && $request['status_code'] < 500) {
                                $statusClass = 'status-4xx';
                            } elseif ($request['status_code'] >= 500) {
                                $statusClass = 'status-5xx';
                            }
                        @endphp
                        <tr class="request-row {{ $statusClass }}" onclick="window.location='{{ route('api-model-relations.debug.show', $request['id']) }}'">
                            <td>{{ \Carbon\Carbon::parse($request['timestamp'])->format('Y-m-d H:i:s') }}</td>
                            <td>{{ $request['method'] }}</td>
                            <td>{{ $request['endpoint'] }}</td>
                            <td>{{ $request['status_code'] }}</td>
                            <td>{{ number_format($request['duration'] * 1000, 2) }} ms</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center">No API requests recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
