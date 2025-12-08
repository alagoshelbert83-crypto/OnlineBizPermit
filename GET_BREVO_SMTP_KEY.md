# How to Get Your Brevo SMTP Key

## Option 1: Generate a New SMTP Key (Recommended)

1. **Log in to Brevo**: https://app.brevo.com

2. **Navigate to SMTP Settings**:
   - Click on your profile icon (top right)
   - Go to **Settings** (or click the gear icon)
   - In the left sidebar, click **SMTP & API**
   - Click on the **SMTP** tab

3. **Generate New SMTP Key**:
   - Look for a button that says **"Generate New SMTP Key"** or **"Create SMTP Key"**
   - Click it
   - Give it a name (e.g., "Vercel API")
   - Click **Generate** or **Create**

4. **Copy the Key Immediately**:
   - ⚠️ **IMPORTANT**: Brevo will show you the full key ONLY ONCE when you first create it
   - The key will look like: `xsmtpib-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx`
   - **Copy the entire key immediately** - you won't be able to see it again!
   - Paste it somewhere safe (like a password manager or text file)

5. **If You Missed It**:
   - If you didn't copy it, you'll need to **delete that key** and **generate a new one**
   - Once generated, only a masked version is shown

## Option 2: Check Existing Keys

1. In **SMTP & API → SMTP** section
2. Look for a list of your SMTP keys
3. If you see keys listed but they're masked (showing `******`), you have two options:
   - **Delete the old key** and create a new one (then copy it immediately)
   - Or if you have the key saved somewhere else, use that

## Option 3: Use API Key Instead (Alternative)

If you can't find SMTP keys, you can also use Brevo's API:
1. Go to **SMTP & API → API Keys**
2. Generate an API key
3. However, this requires code changes to use Brevo's API instead of SMTP

## What the SMTP Key Looks Like:

- **Format**: Starts with `xsmtpib-` followed by a long string
- **Length**: Usually 60-80 characters total
- **Example**: `xsmtpib-1a2b3c4d5e6f7g8h9i0j1k2l3m4n5o6p7q8r9s0t1u2v3w4x5y6z7a8b9c0d1e2f3g4h5i6j7k8l9m0n1o2p3q4r5s6t7u8v9w0x1y2z3`

## After You Get the Key:

1. Copy the **entire key** (from `xsmtpib-` to the end)
2. Go to Vercel → Your Project → Settings → Environment Variables
3. Add: `SMTP_PASS` = `[paste the full key here]`
4. Save and redeploy

---

**Note**: If you're still having trouble, you can also check your email or any documentation where you might have saved the key when you first created your Brevo account.

