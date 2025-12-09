# 🚀 Render PHP Setup - Complete Guide

## ✅ **Configuration Settings for Render:**

### **When Render Asks for Language/Runtime:**

**Select:** `PHP`

Or if Render shows options like:
- ✅ **PHP** (Select this!)
- ❌ Node
- ❌ Python
- ❌ Ruby
- ❌ etc.

---

## 📋 **Complete Render Configuration:**

### **Basic Settings:**
```
Name: onlinebizpermit
Region: Choose closest to you
Branch: main (or your main branch)
```

### **Environment:**
```
Runtime: PHP
```

### **Build & Deploy:**
```
Root Directory: / (leave empty)
Build Command: (leave empty, OR: composer install)
Start Command: php -S 0.0.0.0:$PORT -t .
```

### **Environment Variables:**
```
DATABASE_POSTGRES_URL = postgresql://neondb_owner:npg_8cKZqpe5QJot@ep-weathered-snow-adcxjuz1-pooler.c-2.us-east-1.aws.neon.tech/neondb?sslmode=require
```

---

## ✅ **Quick Reference:**

- **Language:** PHP ✅
- **Start Command:** `php -S 0.0.0.0:$PORT -t .` ✅
- **Build Command:** (empty, or `composer install` if needed)

---

## 🎯 **That's It!**

Select **PHP** and Render will handle the rest! 🚀

