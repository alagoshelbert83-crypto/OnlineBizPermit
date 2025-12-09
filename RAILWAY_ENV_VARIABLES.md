# ✅ Railway Environment Variables Setup

## What Railway Auto-Added

Railway automatically added those variables because it detected PostgreSQL. However, **you're using Neon database, not Railway's database**.

---

## ✅ **What You Need to Do:**

### **Option 1: Use Neon Database (Recommended)**

**Keep these variables:**
- ✅ `DATABASE_POSTGRES_URL` - **SET THIS TO YOUR NEON CONNECTION STRING**

**Set `DATABASE_POSTGRES_URL` to:**
```
DATABASE_POSTGRES_URL=postgresql://neondb_owner:npg_8cKZqpe5QJot@ep-weathered-snow-adcxjuz1-pooler.c-2.us-east-1.aws.neon.tech/neondb?sslmode=require
```

**You can ignore or delete these Railway-generated variables:**
- `DATABASE_PUBLIC_URL`
- `DATABASE_URL`
- `PGDATA`
- `PGDATABASE`
- `PGHOST`
- `PGPASSWORD`
- `PGPORT`
- `PGUSER`
- `POSTGRES_DB`
- `POSTGRES_PASSWORD`
- `POSTGRES_USER`
- `RAILWAY_DEPLOYMENT_DRAINING_SECONDS`
- `SSL_CERT_DAYS`

---

### **Option 2: Use Railway's PostgreSQL (Alternative)**

If you want to use Railway's database instead of Neon:

1. **Delete Neon connection** (or keep it, but don't use it)
2. **Use Railway's auto-generated variables:**
   - Use `DATABASE_URL` or `DATABASE_PUBLIC_URL` (Railway provides these)
3. **Update your PHP files** to use Railway's database connection

**But I recommend Option 1** - stick with Neon since you already have it set up!

---

## 📋 **Step-by-Step Fix:**

### **1. Edit DATABASE_POSTGRES_URL**

In Railway:
1. Go to your service
2. Click **"Variables"** tab
3. Find `DATABASE_POSTGRES_URL`
4. Click **"Edit"** or **"Update"**
5. Set the value to your Neon connection string:
   ```
   postgresql://neondb_owner:npg_8cKZqpe5QJot@ep-weathered-snow-adcxjuz1-pooler.c-2.us-east-1.aws.neon.tech/neondb?sslmode=require
   ```
6. Click **"Save"**

### **2. Remove Railway PostgreSQL Service (If You Added One)**

If you accidentally added a Railway PostgreSQL database:

1. In Railway dashboard, find the **PostgreSQL** service
2. Click on it → Settings → Delete Service
3. This will remove the auto-generated variables (or you can keep them, they won't hurt)

### **3. Add Other Variables You Need**

Also add these (if your PHP uses them):

```
JWT_SECRET=your-jwt-secret-value
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=your-email@gmail.com
SMTP_PASS=your-email-password
```

---

## ✅ **Final Setup:**

**Required Variable:**
- ✅ `DATABASE_POSTGRES_URL` = Your Neon connection string

**Optional Variables (if needed):**
- `JWT_SECRET`
- `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS`
- Firebase credentials (if PHP uses Firebase)

**Ignore/Delete:**
- All Railway auto-generated PostgreSQL variables (unless you're using Railway's database)

---

## 🎯 **Summary:**

1. ✅ **Set `DATABASE_POSTGRES_URL`** to your Neon connection string
2. ✅ **Keep other variables you need** (SMTP, JWT, etc.)
3. ✅ **Ignore Railway's auto-generated PostgreSQL variables** (they're for Railway's database, not Neon)
4. ✅ **Redeploy** after setting variables

---

**Your PHP dashboards will connect to Neon database using `DATABASE_POSTGRES_URL`!** ✅

