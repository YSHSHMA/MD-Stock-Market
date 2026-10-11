<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Daily Portfolio Report</title>
  <style>
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
      line-height: 1.6;
      color: #333;
      max-width: 600px;
      margin: 0 auto;
      padding: 20px;
      background-color: #f5f5f5;
    }

    .container {
      background-color: white;
      border-radius: 8px;
      padding: 30px;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    h1 {
      color: #2563eb;
      margin-top: 0;
      font-size: 24px;
    }

    .greeting {
      font-size: 16px;
      margin-bottom: 20px;
    }

    .report-content {
      background-color: #f8fafc;
      border-left: 4px solid #2563eb;
      padding: 15px;
      margin: 20px 0;
      font-family: 'Courier New', monospace;
      white-space: pre-wrap;
      font-size: 13px;
      line-height: 1.5;
    }

    .footer {
      margin-top: 30px;
      padding-top: 20px;
      border-top: 1px solid #e5e7eb;
      font-size: 12px;
      color: #6b7280;
    }

    .attachment-note {
      background-color: #fef3c7;
      border-left: 4px solid #f59e0b;
      padding: 12px;
      margin: 15px 0;
      font-size: 14px;
    }
  </style>
</head>

<body>
  <div class="container">
    <h1>📊 Daily Portfolio Performance Report</h1>

    <div class="greeting">
      Hello {{ $userName }},
    </div>

    <p>Here's your daily portfolio performance summary for {{ now()->format('l, F j, Y') }}:</p>

    <div class="report-content">{{ $reportText }}</div>

    <div class="attachment-note">
      📎 <strong>CSV Report Attached</strong><br>
      A detailed CSV file with all your portfolio data is attached to this email for your records.
      @if(isset($csvUrl) && $csvUrl)
      <br><br>
      🌐 <strong>Cloud Link:</strong> <a href="{{ $csvUrl }}">Download CSV Report</a>
      @endif
    </div>

    <div class="footer">
      <p>This is an automated report from your Stock Portfolio Tracker.</p>
      <p>If you have any questions, please contact support.</p>
    </div>
  </div>
</body>

</html>