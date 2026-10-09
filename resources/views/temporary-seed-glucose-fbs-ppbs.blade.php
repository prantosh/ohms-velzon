<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Temporary Pathology Template Setup</title>
    <style>
        body { margin: 0; padding: 32px 16px; background: #f3f5f9; color: #202938; font: 16px/1.5 Arial, sans-serif; }
        main { max-width: 620px; margin: 8vh auto; padding: 28px; background: #fff; border: 1px solid #e1e6ef; border-radius: 12px; box-shadow: 0 12px 32px #15223812; }
        h1 { margin-top: 0; font-size: 22px; }
        .notice, .success { padding: 14px; border-radius: 8px; margin: 18px 0; }
        .notice { background: #fff8e6; border: 1px solid #f2d58b; }
        .success { background: #eaf7ef; border: 1px solid #a8d9b7; }
        button { padding: 11px 18px; border: 0; border-radius: 7px; background: #405189; color: white; font-size: 15px; cursor: pointer; }
    </style>
</head>
<body>
<main>
    <h1>Set up GLUCOSE (FBS &amp; PPBS)</h1>
    <p>This one-time action creates or updates the pathology report template and associates items PAT160 and PAT185.</p>

    @if (session('seed_result'))
        <div class="success" role="status">
            Template update completed. Template ID: {{ session('seed_result.template_id') }}.
            Items: {{ implode(', ', session('seed_result.items')) }}.
        </div>
    @else
        <div class="notice">
            Confirm only if you intend to apply this update to the production database.
        </div>
        <form method="POST" action="{{ route('temporary.seed-glucose-fbs-ppbs-template.run') }}">
            @csrf
            <button type="submit">Apply template update</button>
        </form>
    @endif
</main>
</body>
</html>
