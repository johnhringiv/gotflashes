<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password</title>
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
        .button-container {
            text-align: center;
            margin: 32px 0;
        }
        .reset-button {
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
        .expiry-notice {
            background-color: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 12px 16px;
            margin: 24px 0;
            font-size: 14px;
            color: #92400e;
        }
        .security-note {
            padding: 16px;
            background-color: #f9fafb;
            border-radius: 6px;
            font-size: 14px;
            color: #6b7280;
            margin: 24px 0 0 0;
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
            <h2>Reset Your Password</h2>

            <p>Hello {{ $notifiable->first_name }},</p>

            <p>You are receiving this email because we received a password reset request for your G.O.T. Flashes Challenge Tracker account.</p>

            <p>To reset your password, click the button below:</p>

            <div class="button-container">
                <a href="{{ $resetUrl }}" class="reset-button" style="color: #ffffff !important; text-decoration: none !important;">
                    <span style="color: #ffffff !important;">Reset Password</span>
                </a>
            </div>

            <div class="expiry-notice">
                <strong>⏱️ Time Sensitive:</strong> This password reset link will expire in 60 minutes for security purposes.
            </div>

            <p>If the button doesn't work, you can copy and paste this link into your browser:</p>
            <p style="word-break: break-all; color: #09466F; font-size: 14px;">{{ $resetUrl }}</p>

            <div class="security-note">
                <strong>🔒 Security Note:</strong> If you did not request a password reset, no further action is required. Your password will remain unchanged.
            </div>
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
