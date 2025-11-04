<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Award Has Been Sent!</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            background-color: #f3f4f6;
        }
        .email-container {
            max-width: 600px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .email-header {
            background-color: #09466F;
            padding: 40px 20px;
            text-align: center;
        }
        .email-header h1 {
            color: #ffffff;
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        .email-body {
            padding: 40px 30px;
            color: #374151;
        }
        .email-body h2 {
            color: #1f2937;
            font-size: 20px;
            margin: 0 0 20px 0;
        }
        .email-body p {
            margin: 0 0 16px 0;
            font-size: 16px;
        }
        .award-box {
            background: linear-gradient(135deg, #09466F 0%, #0a5a8a 100%);
            padding: 32px;
            border-radius: 8px;
            text-align: center;
            margin: 32px 0;
            box-shadow: 0 4px 6px rgba(9, 70, 111, 0.2);
        }
        .award-box h3 {
            color: #ffffff;
            font-size: 32px;
            margin: 0 0 8px 0;
            font-weight: 700;
        }
        .award-box p {
            color: #e0f2fe;
            font-size: 18px;
            margin: 0;
        }
        .celebration {
            text-align: center;
            font-size: 48px;
            margin: 24px 0;
        }
        .info-box {
            background-color: #f0f9ff;
            border-left: 4px solid #0284c7;
            padding: 16px 20px;
            margin: 24px 0;
            font-size: 14px;
            color: #0c4a6e;
        }
        .info-box p {
            margin: 8px 0;
        }
        .button-container {
            text-align: center;
            margin: 32px 0;
        }
        .view-button {
            display: inline-block;
            padding: 14px 32px;
            background-color: #09466F;
            color: #ffffff;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 16px;
            box-shadow: 0 2px 4px rgba(9, 70, 111, 0.3);
        }
        .email-footer {
            background-color: #f9fafb;
            padding: 30px;
            text-align: center;
            border-top: 1px solid #e5e7eb;
        }
        .email-footer p {
            margin: 8px 0;
            font-size: 14px;
            color: #6b7280;
        }
        .email-footer strong {
            color: #09466F;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="email-header">
            <h1>G.O.T. Flashes</h1>
        </div>

        <!-- Email Body -->
        <div class="email-body">
            <div class="celebration">🎉 🏆 🎊</div>

            <h2>Your Award Is On Its Way!</h2>

            <p>Hi {{ $user->first_name }},</p>

            <p>Congratulations! Your {{ $year }} G.O.T. Flashes {{ $tier }}-Day Award has been sent and is on its way to you!</p>

            <div class="award-box">
                <h3>{{ $tier }}-Day Award</h3>
                <p>{{ $year }} Season</p>
            </div>

            <p>Thank you for your dedication to getting on the water and being an active part of the Lightning Class sailing community. Your commitment to the G.O.T. Flashes Challenge is truly commendable!</p>

            <div class="info-box">
                <p><strong>📬 Delivery Information</strong></p>
                <p>Your award is being sent to the address we have on file. If you've moved or need to update your address, please email admin@gotflashes.com.</p>
            </div>

            <div class="button-container">
                <a href="{{ url('/logbook') }}" class="view-button" style="color: #ffffff !important; text-decoration: none !important;">
                    <span style="color: #ffffff !important;">View Your Logbook</span>
                </a>
            </div>

            <p>Keep logging your sailing days and we'll see you back on the water!</p>

            <p style="margin-top: 24px;">
                <strong>Get Out There!</strong><br>
                The G.O.T. Flashes Team
            </p>
        </div>

        <!-- Footer -->
        <div class="email-footer">
            <p><strong>G.O.T. Flashes Challenge Tracker</strong></p>
            <p><em>Get Out There - FLASHES</em></p>
            <p style="margin-top: 16px;">Encouraging Lightning sailors to get on the water!</p>
        </div>
    </div>
</body>
</html>
