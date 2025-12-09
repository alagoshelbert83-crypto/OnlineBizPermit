# 🚀 Production Deployment Guide

## Overview: Connecting Frontend, Backend & Database for Production

**Your Setup:**
- **Frontend**: Firebase Hosting
- **Backend**: Node.js Express API → Vercel
- **Database**: Neon PostgreSQL
- **PHP Dashboards**: Will deploy to Vercel (they connect directly to Neon)

---

## 📋 Step-by-Step Deployment

### **STEP 1: Verify Vercel Environment Variables** ✅

Your Vercel environment variables should already be set:
- ✅ `DATABASE_POSTGRES_URL` - Neon connection string
- ✅ `FIREBASE_CLIENT_EMAIL`, `FIREBASE_PRIVATE_KEY`, etc. - Firebase credentials
- ✅ `JWT_SECRET` - Authentication secret
- ✅ `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS` - Email config

**Verify in Vercel:**
1. Go to your project → Settings → Environment Variables
2. Ensure all variables are set for **Production**, **Preview**, and **Development**

---

### **STEP 2: Deploy Backend API to Vercel**

```bash
# Make sure you're in the project root
cd c:\Users\dusti\Desktop\onlinebizpermit

# Install Vercel CLI if not installed
npm install -g vercel

# Login to Vercel (if not already)
vercel login

# Deploy (choose production)
vercel --prod
```

**After deployment:**
- You'll get a URL like: `https://online-biz-permit.vercel.app`
- Note this URL - you'll need it for frontend configuration

**Test the deployment:**
```
https://your-project.vercel.app/api/health
https://your-project.vercel.app/api/test/db
```

---

### **STEP 3: Create API Configuration for Frontend**

Create a config file that automatically uses the correct API URL:

```javascript
// config/api-config.js
const API_CONFIG = {
  // Automatically detect environment
  baseURL: (() => {
    // Production: Use Vercel URL
    if (window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
      return 'https://your-project.vercel.app/api';
    }
    // Development: Use localhost
    return 'http://localhost:3000/api';
  })(),
  
  // Helper function to build full URL
  url: (endpoint) => `${API_CONFIG.baseURL}${endpoint}`
};

// Export for use in other files
if (typeof module !== 'undefined' && module.exports) {
  module.exports = API_CONFIG;
}
```

**Update vercel.json to serve static files if needed:**
Your current `vercel.json` only handles API routes. If you need to serve PHP files, you may need additional configuration.

---

### **STEP 4: Update CORS in Backend API**

Your backend already has CORS enabled, but let's make sure it allows your Firebase frontend:

**In `api/index.js`, update CORS configuration:**
```javascript
app.use(cors({
  origin: [
    'https://your-firebase-app.web.app',
    'https://your-firebase-app.firebaseapp.com',
    'http://localhost:3000',
    'http://localhost:5000' // Firebase emulator
  ],
  credentials: true
}));
```

---

### **STEP 5: Update Frontend to Use Backend API**

Since your frontend uses PHP files that connect directly to the database, you have two options:

#### **Option A: Keep PHP Direct Database Connection (Current Setup)**
- ✅ PHP files already configured for Neon
- ✅ Works automatically when deployed to Vercel
- ⚠️ PHP files need to be deployed to Vercel (not Firebase Hosting)

#### **Option B: Update Frontend to Use Node.js API**
- Update JavaScript to call your backend API
- Better separation of concerns
- Requires more frontend changes

**For now, we'll use Option A since your PHP is already set up.**

---

### **STEP 6: Deploy PHP Dashboards to Vercel**

**Good news:** Vercel automatically detects and handles PHP files! 

- ✅ Vercel has built-in PHP runtime
- ✅ No additional packages needed
- ✅ Just deploy - Vercel will detect `.php` files automatically
- ✅ Make sure your `composer.json` has all PHP dependencies listed

**Your `vercel.json` is already configured correctly!** The routes will handle:
- `/api/*` → Node.js API
- All other routes → PHP files (auto-detected)

---

### **STEP 7: Deploy Frontend to Firebase Hosting**

```bash
# Install Firebase CLI if not installed
npm install -g firebase-tools

# Login
firebase login

# Initialize Firebase (if not already)
firebase init hosting

# Deploy
firebase deploy --only hosting
```

**Firebase hosting configuration (`firebase.json`):**
```json
{
  "hosting": {
    "public": "public",
    "ignore": [
      "firebase.json",
      "**/.*",
      "**/node_modules/**"
    ],
    "rewrites": [
      {
        "source": "**",
        "destination": "/index.html"
      }
    ]
  }
}
```

---

### **STEP 8: Run Database Schema**

Make sure your Neon database has all tables:

1. Go to Vercel → Storage → Your Neon Database
2. Open Neon SQL Editor
3. Run your `supabase_schema.sql` file

---

### **STEP 9: Test Everything**

**Test Backend API:**
```bash
# Health check
curl https://your-project.vercel.app/api/health

# Database connection
curl https://your-project.vercel.app/api/test/db
```

**Test Frontend:**
- Visit your Firebase hosting URL
- Try logging in
- Check that data loads from database

**Test PHP Dashboards:**
- Visit your Vercel URL
- Access PHP dashboards
- Verify database connections work

---

## 🔧 Configuration Files Needed

### 1. Update `vercel.json` for PHP Support

### 2. Create `.vercelignore` (optional)
```
node_modules
.git
.env.local
*.log
```

### 3. Update `package.json` scripts
```json
{
  "scripts": {
    "start": "node api/index.js",
    "dev": "node api/index.js",
    "deploy": "vercel --prod"
  }
}
```

---

## 🎯 Final Architecture

```
┌─────────────────┐
│  Firebase       │
│  Hosting        │─────┐
│  (Frontend)     │     │
└─────────────────┘     │
                        │
┌─────────────────┐     │     ┌─────────────────┐
│  Vercel         │     │     │  Neon           │
│  ┌───────────┐  │     │     │  PostgreSQL     │
│  │ Node.js   │  │─────┼─────▶  (Database)     │
│  │ API       │  │     │     │                 │
│  └───────────┘  │     │     └─────────────────┘
│  ┌───────────┐  │     │
│  │ PHP       │──┘     │
│  │ Dashboards│        │
│  └───────────┘        │
└─────────────────┘     │
                        │
                 API Calls
```

---

## ✅ Checklist

- [ ] Environment variables set in Vercel
- [ ] Backend API deployed to Vercel
- [ ] Database schema imported to Neon
- [ ] CORS configured for frontend domain
- [ ] PHP files ready (Vercel auto-detects PHP - no package needed)
- [ ] `vercel.json` updated for PHP support
- [ ] Frontend deployed to Firebase Hosting
- [ ] Test all endpoints
- [ ] Test login/authentication
- [ ] Test database connections

---

## 🐛 Troubleshooting

### Backend not connecting to database
- Check `DATABASE_POSTGRES_URL` in Vercel environment variables
- Verify Neon database is active (not sleeping)

### CORS errors
- Update CORS origin in `api/index.js` to include your Firebase domain
- Check browser console for specific error messages

### PHP files not working on Vercel
- Ensure `@vercel/php` is installed
- Check `vercel.json` has PHP routes configured
- Verify `composer.json` has all dependencies

### Frontend can't reach backend
- Verify backend URL is correct in frontend config
- Check that backend is deployed and accessible
- Test backend endpoints directly in browser

---

## 📞 Next Steps

1. Deploy backend to Vercel
2. Update `vercel.json` for PHP
3. Deploy PHP dashboards
4. Deploy frontend to Firebase
5. Test everything

Ready to start? Let's begin with Step 1!

