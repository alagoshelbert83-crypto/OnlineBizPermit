# Vercel Setup Guide for Online Business Permit System

## Step 1: Login to Vercel

1. Go to https://vercel.com/login
2. Sign in using one of these methods:
   - **GitHub** (Recommended if your code is on GitHub)
   - **Google**
   - **Email**
   - **SAML SSO** (for enterprise)

## Step 2: Import Your Project

### Option A: If your project is on GitHub/GitLab/Bitbucket

1. After logging in, click **"Add New..."** → **"Project"**
2. Click **"Import Git Repository"**
3. Select your Git provider (GitHub, GitLab, or Bitbucket)
4. Authorize Vercel to access your repositories
5. Find and select your `onlinebizpermit` repository
6. Click **"Import"**

### Option B: If your project is NOT on GitHub yet

**First, push to GitHub:**
```bash
# Initialize git (if not already done)
git init

# Add all files
git add .

# Commit
git commit -m "Initial commit"

# Create a new repository on GitHub, then:
git remote add origin https://github.com/YOUR_USERNAME/onlinebizpermit.git
git branch -M main
git push -u origin main
```

Then follow **Option A** above.

## Step 3: Configure Project Settings

When importing, Vercel will auto-detect your settings. Verify:

- **Framework Preset:** `Other` (or leave blank)
- **Root Directory:** `./` (root)
- **Build Command:** `echo 'Node.js project - no build needed'` (or leave empty)
- **Output Directory:** Leave empty
- **Install Command:** `npm install`

## Step 4: Set Environment Variables

**CRITICAL:** Add these environment variables in Vercel Dashboard:

1. Go to your project → **Settings** → **Environment Variables**

2. Add the following variables:

   ```
   DATABASE_URL=your_neon_postgresql_connection_string
   JWT_SECRET=your_secure_random_secret_key
   SMTP_HOST=your_smtp_host
   SMTP_PORT=587
   SMTP_USER=your_smtp_username
   SMTP_PASS=your_smtp_password
   ```

   **Important Notes:**
   - `DATABASE_URL`: Your Neon PostgreSQL connection string (format: `postgresql://user:password@host/database?sslmode=require`)
   - `JWT_SECRET`: Generate a secure random string (e.g., use `openssl rand -base64 32`)
   - SMTP settings: Your email service credentials (Gmail, SendGrid, etc.)

3. Make sure to add these for **Production**, **Preview**, and **Development** environments

## Step 5: Deploy

1. Click **"Deploy"** button
2. Wait for the deployment to complete (usually 1-2 minutes)
3. Your API will be available at: `https://your-project-name.vercel.app`

## Step 6: Verify Deployment

Test these endpoints:

1. **Health Check:**
   ```
   https://your-project-name.vercel.app/api/health
   ```
   Should return: `{"status":"OK","timestamp":"..."}`

2. **Root URL:**
   ```
   https://your-project-name.vercel.app/
   ```
   Should show the landing page with dashboard links

3. **API Endpoints:**
   - `POST /api/auth/signup` - User registration
   - `POST /api/auth/login` - Applicant login
   - `POST /api/auth/staff-login` - Staff/Admin login

## Step 7: Update CORS Origins (if needed)

If you have a custom domain or different frontend URLs, update the `allowedOrigins` array in `api/index.js` to include:
- Your Vercel deployment URL
- Your custom domain (if any)
- Your frontend URLs

## Troubleshooting

### Deployment Fails
- Check that all environment variables are set
- Verify `package.json` has all dependencies
- Check build logs in Vercel dashboard

### API Returns 500 Errors
- Check environment variables are correctly set
- Verify database connection string is correct
- Check function logs in Vercel dashboard

### CORS Errors
- Add your frontend domain to `allowedOrigins` in `api/index.js`
- Ensure `VERCEL_URL` environment variable is available (Vercel sets this automatically)

## Current Configuration Summary

✅ **Vercel Configuration:**
- Runtime: Node.js 24.x
- Function: `api/index.js`
- Rewrite: Root `/` → `/api`

✅ **Project Structure:**
- API endpoint: `api/index.js` (Express server)
- Configuration: `vercel.json`
- PHP files: Excluded via `.vercelignore`

## Next Steps After Deployment

1. **Test all API endpoints** with your frontend
2. **Set up a custom domain** (optional) in Vercel project settings
3. **Configure monitoring** in Vercel dashboard
4. **Set up environment-specific variables** for staging/production

---

**Need Help?**
- Vercel Docs: https://vercel.com/docs
- Vercel Support: https://vercel.com/help

