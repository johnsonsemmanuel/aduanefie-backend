<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subject }}</title>
    <style>
        body { font-family: 'Roboto', sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; }
        .container { width: 100%; max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 40px; border-radius: 8px; }
        .header { text-align: center; padding-bottom: 20px; border-bottom: 2px solid #2e7d32; margin-bottom: 30px; }
        .header h1 { color: #2e7d32; margin: 0; font-size: 24px; }
        .content { line-height: 1.6; color: #333; }
        .content h2 { color: #2e7d32; font-size: 20px; margin-top: 0; }
        .btn { display: inline-block; background-color: #2e7d32; color: #ffffff !important; text-decoration: none; padding: 12px 24px; border-radius: 4px; font-weight: bold; margin-top: 20px; }
        .badge { display: inline-block; background-color: #e8f5e9; color: #2e7d32; padding: 6px 12px; border-radius: 4px; font-size: 12px; font-weight: bold; margin-right: 8px; margin-bottom: 8px; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #e0e0e0; font-size: 12px; color: #777; text-align: center; }
        .info-box { background-color: #f1f8e9; padding: 15px; border-radius: 4px; border-left: 4px solid #2e7d32; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🌿 Aduanefie Backyard Farming</h1>
        </div>

        <div class="content">
            {!! $content !!}
        </div>

        <div class="footer">
            <p>© {{ date('Y') }} Aduanefie. All rights reserved.</p>
            <p>
                <a href="{{ config('app.url', 'https://aduanefie.com') }}" style="color: #2e7d32; text-decoration: none;">Visit Platform</a>
                &nbsp;|&nbsp;
                <a href="{{ config('app.url', 'https://aduanefie.com') }}/backyard-farmer/apply" style="color: #2e7d32; text-decoration: none;">Apply to Farm</a>
            </p>
        </div>
    </div>
</body>
</html>
