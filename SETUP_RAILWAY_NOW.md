# 🚂 Setup Railway for PHP Dashboards

## ✅ **Files Created:**

1. ✅ `Procfile` - Tells Railway how to run PHP
2. ✅ `railway.json` - Railway configuration

## 🚀 **Quick Setup Steps:**

### **Step 1: Create Railway Account**
1. Go to [railway.app](https://railway.app)
2. Click "Start a New Project" or "Login"
3. Sign up with GitHub (easiest)

### **Step 2: Deploy Your Repo**
1. Click "New Project"
2. Select "Deploy from GitHub repo"
3. Choose your `onlinebizpermit` repository
4. Railway will detect PHP automatically

### **Step 3: Add Environment Variable**
1. In Railway project, click your service
2. Go to "Variables" tab
3. Click "New Variable"
4. Add:
   ```
   DATABASE_POSTGRES_URL=postgresql://neondb_owner:npg_8cKZqpe5QJot@ep-weathered-snow-adcxjuz1-pooler.c-2.us-east-1.aws.neon.tech/neondb?sslmode=require
   ```
5. Click "Add"

### **Step 4: Deploy**
- Railway will automatically deploy
- Watch the deployment logs
- Wait for it to finish (2-5 minutes)

### **Step 5: Get Your URL**
1. Railway will give you a URL like: `https://your-app.up.railway.app`
2. Your dashboards will be at:
   - `https://your-app.up.railway.app/Applicant-dashboard/`
   - `https://your-app.up.railway.app/Staff-dashboard/`
   - `https://your-app.up.railway.app/Admin-dashboard/`

### **Step 6: Update Landing Page**
Update `public/index.html` dashboard links to your Railway URL, then redeploy to Firebase.

---

## ✅ **After Deployment:**

**Your complete production setup:**

1. **Landing Page:** `https://onlinebizpermit.web.app` (Firebase)
2. **Backend API:** `https://online-biz-permit.vercel.app/api/*` (Vercel)
3. **PHP Dashboards:** `https://your-app.railway.app/*` (Railway)
4. **Database:** Neon PostgreSQL (shared by all)

**Everything connected and working!** 🎉

---

## 📋 **What Railway Does:**

Railway will:
- ✅ Run PHP server
- ✅ Execute your PHP code
- ✅ Serve all dashboard files
- ✅ Connect to Neon database
- ✅ Handle all PHP functionality

**Your PHP dashboards will work perfectly!** ✅

