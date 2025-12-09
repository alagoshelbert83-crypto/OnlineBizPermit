# ✅ Fix Railway Variables Setup

## 🎯 **What You Need to Do:**

### **Step 1: Click on the Right Service**

In the left sidebar, click on:
- ✅ **"OnlineBizPermit"** (GitHub icon, currently shows "Service is offline")
- ❌ NOT "Postgres" (this is Railway's database, you don't need it)

### **Step 2: Go to Variables Tab**

Once you're in the "OnlineBizPermit" service:
1. Click the **"Variables"** tab
2. You should see an empty variables list (or very few variables)

### **Step 3: Add Your Neon Database Variable**

Click **"New Variable"** or **"Raw Editor"** and add:

**Variable Name:**
```
DATABASE_POSTGRES_URL
```

**Variable Value:**
```
postgresql://neondb_owner:npg_8cKZqpe5QJot@ep-weathered-snow-adcxjuz1-pooler.c-2.us-east-1.aws.neon.tech/neondb?sslmode=require
```

Click **"Add"** or **"Save"**

### **Step 4: Add Other Variables (If Needed)**

Also add these if your PHP uses them:

```
JWT_SECRET=your-secret-key
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=your-email@gmail.com
SMTP_PASS=your-password
```

### **Step 5: About the Postgres Service**

The "Postgres" service you see is **Railway's own database** - you don't need it since you're using Neon!

**You can:**
- ✅ Leave it (won't hurt, just uses resources)
- ✅ Delete it to save resources (Settings → Delete Service)

---

## ✅ **Summary:**

1. ✅ Click **"OnlineBizPermit"** service (not Postgres)
2. ✅ Go to **Variables** tab
3. ✅ Add `DATABASE_POSTGRES_URL` with your Neon connection string
4. ✅ Add other variables as needed
5. ✅ Your PHP app will connect to Neon database!

---

## 🚨 **Important:**

- **Postgres service variables** = Railway's database (not needed)
- **OnlineBizPermit service variables** = Your app's variables (NEEDED!)

Make sure you're adding variables to the **OnlineBizPermit** service! 🎯

