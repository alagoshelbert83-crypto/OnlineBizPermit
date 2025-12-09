# 🔧 Fix Database Connection Error

## ❌ **Problem:**

**Error:** "Database connection failed"

**This means:** Your PHP dashboards are deployed, but can't connect to Neon database.

---

## ✅ **Solution: Add Environment Variable in Render**

### **Step 1: Go to Render Dashboard**

1. Go to your Render service: `OnlineBizPermit`
2. Click on the service
3. Go to **"Environment"** tab (or "Variables" tab)

### **Step 2: Add DATABASE_POSTGRES_URL**

Click **"Add Environment Variable"** and add:

**Key:**
```
DATABASE_POSTGRES_URL
```

**Value:**
```
postgresql://neondb_owner:npg_8cKZqpe5QJot@ep-weathered-snow-adcxjuz1-pooler.c-2.us-east-1.aws.neon.tech/neondb?sslmode=require
```

**Important:** Copy the ENTIRE connection string exactly!

### **Step 3: Save and Redeploy**

1. Click **"Save Changes"**
2. Render will automatically redeploy
3. Wait for deployment to complete

---

## ✅ **Verify Database Schema**

Make sure your database has all tables:

1. Go to **Vercel Dashboard** → Storage → Neon Database
2. Click **"SQL Editor"**
3. Run your `supabase_schema.sql` file
4. Verify tables are created (users, applications, documents, etc.)

**Without database tables, your app won't work!**

---

## 🔍 **Check Connection String Format**

Your connection string should look like:
```
postgresql://username:password@host:port/database?sslmode=require
```

Make sure:
- ✅ No extra spaces
- ✅ All special characters are correct
- ✅ `sslmode=require` is included (Neon requires SSL)

---

## ✅ **After Adding Environment Variable:**

1. ✅ Render will redeploy automatically
2. ✅ PHP dashboards will connect to Neon
3. ✅ Database connection will work
4. ✅ You can log in and use dashboards

---

## 🎯 **Quick Checklist:**

- [ ] Environment variable `DATABASE_POSTGRES_URL` added in Render
- [ ] Connection string is correct (copy from Vercel)
- [ ] Database schema imported (run `supabase_schema.sql`)
- [ ] Service redeployed after adding variable
- [ ] Test login on dashboards

---

**Add the environment variable in Render and redeploy!** ✅

