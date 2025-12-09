# 🚂 Deploy PHP Dashboards to Railway

## ✅ **Simple 5-Step Setup:**

### **Step 1: Create Railway Account**
1. Go to [railway.app](https://railway.app)
2. Sign up with GitHub (free)

### **Step 2: Create New Project**
1. Click "New Project"
2. Select "Deploy from GitHub repo"
3. Select your `onlinebizpermit` repository

### **Step 3: Railway Auto-Detects PHP**
- Railway will automatically detect your PHP files
- The `Procfile` and `railway.json` help configure it

### **Step 4: Add Environment Variables**
In Railway project settings, add:
```
DATABASE_POSTGRES_URL=postgresql://neondb_owner:npg_8cKZqpe5QJot@ep-weathered-snow-adcxjuz1-pooler.c-2.us-east-1.aws.neon.tech/neondb?sslmode=require
```

And any other env vars you need:
- `JWT_SECRET`
- `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS`
- Firebase credentials (if needed)

### **Step 5: Deploy!**
- Railway will automatically deploy
- You'll get a URL like: `https://your-app.railway.app`

---

## 🎯 **After Deployment:**

**Your dashboard URLs will be:**
- ✅ **Applicant:** `https://your-app.railway.app/Applicant-dashboard/`
- ✅ **Staff:** `https://your-app.railway.app/Staff-dashboard/`
- ✅ **Admin:** `https://your-app.railway.app/Admin-dashboard/`

**Your API stays on Vercel:**
- ✅ **API:** `https://online-biz-permit.vercel.app/api/*`

---

## 📋 **Files Created for Railway:**

1. ✅ `Procfile` - Tells Railway how to run PHP
2. ✅ `railway.json` - Railway configuration

These files are ready - just deploy to Railway!

---

## 💡 **Alternative: Render.com**

If Railway doesn't work, try Render:
1. Go to [render.com](https://render.com)
2. New → Web Service
3. Connect GitHub
4. Select "PHP" as runtime
5. Add environment variables
6. Deploy!

---

**Ready to deploy to Railway?** Just connect your GitHub repo! 🚀

