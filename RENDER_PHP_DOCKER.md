# 🐳 Deploy PHP to Render Using Docker

## ❌ **Issue: PHP Not in Language Dropdown**

Render may require using Docker for PHP deployment instead of direct PHP runtime.

---

## ✅ **Solution: Use Docker**

### **Option 1: Create Dockerfile**

Create a `Dockerfile` in your project root:

```dockerfile
FROM php:8.2-apache

# Install PostgreSQL extension
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

# Copy application files
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html

# Expose port
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
```

### **Option 2: Use render.yaml**

Your `render.yaml` file should work:

```yaml
services:
  - type: web
    name: onlinebizpermit
    runtime: docker
    dockerfilePath: ./Dockerfile
    envVars:
      - key: DATABASE_POSTGRES_URL
        sync: false
```

---

## 📋 **Steps:**

1. **Select "Docker" from Language dropdown**
2. **Render will use your Dockerfile** (if it exists)
3. **Or create Dockerfile** (see above)
4. **Deploy!**

---

## ✅ **Alternative: Check All Options**

The dropdown might need scrolling - PHP could be further down the list!

Try:
1. Scroll down in the dropdown
2. Type "PHP" in the search/filter
3. Or select "Docker" and we'll create a Dockerfile

---

## 🎯 **Quick Fix:**

**If PHP isn't showing:**
1. Select **"Docker"** from dropdown
2. I'll create a Dockerfile for you
3. Render will use Docker to run PHP

**Let me know if you see PHP after scrolling, or we'll use Docker!**

