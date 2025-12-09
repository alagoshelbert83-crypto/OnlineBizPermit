# 📝 Add Dockerfile to GitHub - Instructions

## 🎯 **Option 1: Push via Your IDE/GitHub Desktop**

Use your preferred Git tool to:
1. Stage `Dockerfile`
2. Commit with message: "Add Dockerfile for Render deployment"
3. Push to GitHub

---

## 🎯 **Option 2: Add via GitHub Website**

### **Step 1: Go to GitHub**
Visit: `https://github.com/alagoshelbert83-crypto/OnlineBizPermit`

### **Step 2: Create Dockerfile**
1. Click **"Add file"** → **"Create new file"**
2. File name: `Dockerfile` (exactly this name, case-sensitive)

### **Step 3: Copy This Content:**

```dockerfile
FROM php:8.2-apache

# Install PostgreSQL extension and required packages
RUN apt-get update && apt-get install -y \
    libpq-dev \
    git \
    unzip \
    && docker-php-ext-install pdo pdo_pgsql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Expose port 80
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
```

### **Step 4: Commit**
1. Scroll down
2. Commit message: `Add Dockerfile for Render deployment`
3. Click **"Commit new file"**

### **Step 5: Trigger Render Deployment**
1. Go back to Render dashboard
2. Click **"Manual Deploy"** → **"Deploy latest commit"**
3. Render will find Dockerfile and deploy!

---

## ✅ **After Adding to GitHub:**

1. ✅ Dockerfile is in repository
2. ✅ Render can find it
3. ✅ Deployment will succeed
4. ✅ Your PHP dashboards will be live!

---

**Add the Dockerfile to GitHub, then redeploy in Render!** 🚀

