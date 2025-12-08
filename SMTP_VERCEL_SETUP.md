# SMTP Configuration for Vercel

## Add These Environment Variables in Vercel:

1. Go to: **Your Project → Settings → Environment Variables**

2. Add these 4 variables:

### Required SMTP Variables:

| Variable Name | Value |
|--------------|-------|
| `SMTP_HOST` | `smtp-relay.brevo.com` |
| `SMTP_PORT` | `587` |
| `SMTP_USER` | `9a9125001@smtp-brevo.com` |
| `SMTP_PASS` | `[Your full Brevo SMTP Key]` |

## Important Notes:

⚠️ **SMTP_PASS**: Make sure you use the **FULL, UNMASKED** Brevo SMTP key from your Brevo dashboard. The key should look like:
- Format: `xsmtpib-` followed by a long string of characters
- Example: `xsmtpib-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx`

## How to Get Your Full SMTP Key:

1. Log in to Brevo: https://app.brevo.com
2. Navigate to: **Settings** → **SMTP & API** → **SMTP**
3. Find your SMTP key (or generate a new one)
4. Click to **reveal/copy** the full key (not the masked version)
5. Paste the complete key into Vercel's `SMTP_PASS` variable

## After Adding Variables:

1. **Redeploy** your project for changes to take effect
2. Test email functionality by using your API endpoints

## Testing:

Once deployed, you can test email functionality through your API endpoints that use the email transporter.

