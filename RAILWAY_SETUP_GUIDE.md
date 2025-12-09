# 🚂 Complete Railway Setup Guide

## Step-by-Step Instructions to Deploy Your PHP Dashboards

---

## **Step 1: Create Railway Account**

1. Go to **[railway.app](https://railway.app)**
2. Click **"Start a New Project"** or **"Login"**
3. Sign up using one of these options:
   - ✅ **GitHub** (Recommended - easiest)
   - ✅ Google
   - ✅ Email

4. If using GitHub:
   - Click **"Login with GitHub"**
   - Authorize Railway to access your GitHub account
   - Complete the signup process

---

## **Step 2: Create a New Project**

1. Once logged in, you'll see the Railway dashboard
2. Click the **"New Project"** button (usually a green button or "+" icon)
3. Select **"Deploy from GitHub repo"**
4. You'll see a list of your GitHub repositories
5. Select **`onlinebizpermit`** (or whatever your repo is named)
6. Railway will start importing your project

---

## **Step 3: Configure the Project**

### **3a. Railway Auto-Detection**
- Railway will automatically detect your PHP files
- It uses the `Procfile` and `railway.json` files we created
- Wait for Railway to finish scanning your project

### **3b. Select Service Type**
- Railway may ask what type of service this is
- Select **"Web Service"** or **"PHP"**

---

## **Step 4: Add Environment Variables**

This is **CRITICAL** - your app needs these to connect to the database!

1. In your Railway project, click on your service
2. Go to the **"Variables"** tab
3. Click **"New Variable"** or **"Raw Editor"**
4. Add these environment variables:

```
DATABASE_POSTGRES_URL=postgresql://neondb_owner:npg_8cKZqpe5QJot@ep-weathered-snow-adcxjuz1-pooler.c-2.us-east-1.aws.neon.tech/neondb?sslmode=require
```

**To add more variables, click "New Variable" again:**

```
JWT_SECRET=your-jwt-secret-here
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=your-email@gmail.com
SMTP_PASS=your-email-password
```

**Optional Firebase variables (if your PHP uses Firebase):**
```
FIREBASE_PRIVATE_KEY_ID=your-key-id
FIREBASE_PRIVATE_KEY=your-private-key
FIREBASE_CLIENT_EMAIL=your-client-email
FIREBASE_CLIENT_ID=your-client-id
FIREBASE_CLIENT_X509_CERT_URL=your-cert-url
```

5. Click **"Save"** or **"Update Variables"**

---

## **Step 5: Configure Deploy Settings**

1. In your service settings, go to **"Settings"** tab
2. Check **"Start Command"** - it should be:
   ```
   php -S 0.0.0.0:$PORT -t .
   ```
   (This is already in your `Procfile`, so Railway should auto-detect it)

3. Check **"Root Directory"** - leave it as `/` (root)

---

## **Step 6: Deploy!**

1. Railway will automatically start deploying
2. You can watch the deployment logs in real-time
3. Wait for it to finish (usually 2-5 minutes)
4. Once deployed, you'll see a **"Generate Domain"** button or your app URL

---

## **Step 7: Get Your App URL**

1. In your Railway service, go to the **"Settings"** tab
2. Scroll down to **"Networking"** or **"Domains"**
3. Railway will generate a default domain like:
   - `https://your-app-name.up.railway.app`
4. Or you can add a custom domain later

---

## **Step 8: Test Your Dashboards**

Once deployed, test these URLs:

✅ **Applicant Dashboard:**
```
https://your-app-name.up.railway.app/Applicant-dashboard/
```

✅ **Staff Dashboard:**
```
https://your-app-name.up.railway.app/Staff-dashboard/
```

✅ **Admin Dashboard:**
```
https://your-app-name.up.railway.app/Admin-dashboard/
```

---

## **Step 9: Verify Database Connection**

1. Try logging in to any dashboard
2. If you see a database connection error, check:
   - Environment variables are set correctly
   - `DATABASE_POSTGRES_URL` matches your Neon database
   - Database schema is imported (run `supabase_schema.sql` in Neon)

---

## ✅ **Troubleshooting**

### **Problem: App won't start**
- **Solution:** Check the deployment logs for errors
- Make sure `Procfile` exists in your repo
- Verify PHP is detected correctly

### **Problem: Database connection fails**
- **Solution:** 
  1. Verify `DATABASE_POSTGRES_URL` is set correctly
  2. Check that Neon database is active (not sleeping)
  3. Ensure database schema is imported

### **Problem: 404 errors on dashboard pages**
- **Solution:**
  1. Check that all PHP files are in the repo
  2. Verify `.htaccess` or routing is correct
  3. Check Railway logs for specific errors

### **Problem: Environment variables not working**
- **Solution:**
  1. Make sure variables are saved in Railway
  2. Redeploy after adding new variables
  3. Check variable names match what PHP expects

---

## 📋 **Quick Checklist**

Before deploying, make sure you have:
- [ ] Railway account created
- [ ] GitHub repo connected
- [ ] `Procfile` file in your repo ✅ (already created)
- [ ] `railway.json` file in your repo ✅ (already created)
- [ ] Environment variables added
- [ ] Database schema imported to Neon

---

## 🎯 **After Successful Deployment**

Your final architecture:

```
┌─────────────────────────┐
│  Vercel                 │
│  Node.js API            │
│  /api/*                 │
│  https://online-biz-    │
│  permit.vercel.app      │
└─────────────────────────┘

┌─────────────────────────┐
│  Railway                │
│  PHP Dashboards         │
│  https://your-app.      │
│  railway.app            │
└─────────────────────────┘
           │
           │ Both connect to
           ▼
┌─────────────────────────┐
│  Neon PostgreSQL        │
│  Database               │
└─────────────────────────┘
```

---

## 💡 **Pro Tips**

1. **Free Tier:** Railway gives you $5 free credit/month - enough for small apps!
2. **Auto-Deploy:** Railway auto-deploys when you push to GitHub
3. **Logs:** Check deployment logs if something doesn't work
4. **Variables:** Keep sensitive data in environment variables, never in code
5. **Database:** Your Neon database is shared between Vercel and Railway

---

## 🚀 **Ready to Deploy?**

1. Make sure your code is pushed to GitHub
2. Follow steps 1-7 above
3. Test your dashboards
4. Done! 🎉

---

**Need help with any step?** Let me know which step you're on and I'll help!

