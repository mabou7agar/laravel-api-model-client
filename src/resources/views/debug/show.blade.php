<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Request Details - API Model Relations</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/themes/prism.min.css" rel="stylesheet">
    <style>
        pre {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
        }
        .status-2xx { color: #28a745; }
        .status-3xx { color: #ffc107; }
        .status-4xx { color: #dc3545; }
        .status-5xx { color: #dc3545; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>API Request Details</h1>
            <div>
                <a href="{{ route('api-model-relations.debug.index') }}" class="btn btn-secondary">Back to Dashboard</a>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <div class="d-flex justify-content-between">
                    <h5 class="mb-0">
                        <span class="badge bg-primary">{{ $request['method'] }}</span>
                        {{ $request['endpoint'] }}
                    </h5>
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
                    <span class="{{ $statusClass }} fw-bold">Status: {{ $request['status_code'] }}</span>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Request ID:</strong> {{ $request['id'] }}</p>
                        <p><strong>Timestamp:</strong> {{ \Carbon\Carbon::parse($request['timestamp'])->format('Y-m-d H:i:s') }}</p>
                        <p><strong>Duration:</strong> {{ number_format($request['duration'] * 1000, 2) }} ms</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Success:</strong> <span class="badge {{ $request['success'] ? 'bg-success' : 'bg-danger' }}">{{ $request['success'] ? 'Yes' : 'No' }}</span></p>
                        @if(isset($request['error']))
                            <p><strong>Error:</strong> {{ $request['error'] }}</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        Request Options
                    </div>
                    <div class="card-body">
                        <pre><code class="language-json">{{ json_encode($request['options'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        Response
                    </div>
                    <div class="card-body">
                        @if(isset($request['response']['_truncated']) && $request['response']['_truncated'])
                            <div class="alert alert-warning">
                                {{ $request['response']['summary'] }}
                            </div>
                        @else
                            <pre><code class="language-json">{{ json_encode($request['response'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/prism.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/components/prism-json.min.js"></script>
</body>
</html>
