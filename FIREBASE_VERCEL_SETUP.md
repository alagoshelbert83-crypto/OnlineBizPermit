# 🔥 Firebase + Vercel Setup Guide

## Architecture Overview

```
┌─────────────────────┐
│  Firebase Hosting   │  → Static Frontend (HTML/CSS/JS)
│  Frontend           │     Calls Vercel API
└──────────┬──────────┘
           │
           │ API Calls
           ↓
┌─────────────────────┐
│  Vercel             │  → Node.js Backend API
│  Backend API        │     Connects to Database
└──────────┬──────────┘
           │
           ↓
┌─────────────────────┐
│  Neon PostgreSQL    │  → Database
│  Database           │
└─────────────────────┘
```

---

## ✅ **What's Already Done:**

- ✅ **Backend API on Vercel:** `https://online-biz-permit.vercel.app/api/*`
- ✅ **Database:** Neon PostgreSQL (connected)
- ✅ **Environment Variables:** Set in Vercel

---

## 📋 **Setup Steps:**

### **STEP 1: Update CORS in Your API**

Your API needs to allow requests from Firebase Hosting.

**Update `api/index.js`:**

```javascript
app.use(cors({
  origin: [
    'https://onlinebizpermit.web.app',
    'https://onlinebizpermit.firebaseapp.com',
    'https://online-biz-permit.vercel.app',
    'http://localhost:3000',
    'http://localhost:5000'
  ],
  credentials: true
}));
```

Already configured! ✅ Just verify your Firebase domains are included.

---

### **STEP 2: Initialize Firebase Hosting**

```bash
# Install Firebase CLI (if not installed)
npm install -g firebase-tools

# Login to Firebase
firebase login

# Initialize Firebase Hosting in your project
firebase init hosting
```

**When prompted:**
- **Select "Use an existing project"** → Choose `onlinebizpermit`
- **Public directory:** `public` (we'll create this)
- **Single-page app:** `No` (unless you want SPA routing)
- **Set up automatic builds:** `No`
- **File overwrite:** Choose based on existing files

---

### **STEP 3: Create Public Directory for Static Files**

You need to create a `public` folder with your frontend files.

**Option A: Create Simple Frontend (Recommended)**

Create a `public` folder with an `index.html` that uses your API:

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Business Permit System</title>
    <script src="https://www.gstatic.com/firebasejs/9.x.x/firebase-app.js"></script>
    <script src="https://www.gstatic.com/firebasejs/9.x.x/firebase-auth.js"></script>
    <script src="firebase-config.js"></script>
    <script src="api-config.js"></script>
</head>
<body>
    <!-- Your frontend UI here -->
    <!-- Use API_CONFIG.url('/auth/login') to call your API -->
</body>
</html>
```

**Option B: Copy Static Assets**

If you have static HTML/CSS/JS files, copy them to `public` folder:
- HTML files
- CSS files  
- JavaScript files
- Images
- `firebase-config.js`
- `api-config.js`

**⚠️ Note:** PHP files CANNOT go to Firebase Hosting - they need to stay on Railway or Vercel.

---

### **STEP 4: Update api-config.js for Production**

Update `api-config.js` to use your Vercel URL:

```javascript
const API_CONFIG = {
  baseURL: (() => {
    const vercelUrl = 'https://online-biz-permit.vercel.app';
    const isProduction = window.location.hostname !== 'localhost' && 
                        window.location.hostname !== '127.0.0.1';
    
    if (isProduction) {
      return `${vercelUrl}/api`;
    }
    
    return 'http://localhost:3000/api';
  })(),
  
  url: (endpoint) => {
    const cleanEndpoint = endpoint.startsWith('/') ? endpoint : `/${endpoint}`;
    return `${API_CONFIG.baseURL}${cleanEndpoint}`;
  },
  
  fetch: async (endpoint, options = {}) => {
    const token = localStorage.getItem('authToken');
    const headers = {
      'Content-Type': 'application/json',
      ...options.headers
    };
    
    if (token) {
      headers['Authorization'] = `Bearer ${token}`;
    }
    
    const response = await fetch(API_CONFIG.url(endpoint), {
      ...options,
      headers
    });
    
    if (!response.ok) {
      const error = await response.json().catch(() => ({ message: 'Request failed' }));
      throw new Error(error.message || `HTTP ${response.status}`);
    }
    
    return response.json();
  }
};

window.API_CONFIG = API_CONFIG;
```

---

### **STEP 5: Configure Firebase Hosting**

Create/update `firebase.json`:

```json
{
  "hosting": {
    "public": "public",
    "ignore": [
      "firebase.json",
      "**/.*",
      "**/node_modules/**",
      "**/*.php",
      "**/api/**",
      "**/Applicant-dashboard/**",
      "**/Staff-dashboard/**",
      "**/Admin-dashboard/**"
    ],
    "rewrites": [
      {
        "source": "**",
        "destination": "/index.html"
      }
    ],
    "headers": [
      {
        "source": "**/*.@(jpg|jpeg|gif|png|svg|webp|js|css)",
        "headers": [
          {
            "key": "Cache-Control",
            "value": "max-age=31536000"
          }
        ]
      }
    ]
  }
}
```

---

### **STEP 6: Deploy to Firebase Hosting**

```bash
# Deploy your frontend
firebase deploy --only hosting
```

After deployment, you'll get URLs like:
- `https://onlinebizpermit.web.app`
- `https://onlinebizpermit.firebaseapp.com`

---

## ⚠️ **Important: PHP Dashboards**

**Firebase Hosting CANNOT run PHP files.**

You have two options:

### **Option 1: Keep PHP on Railway** (Current Setup)
- ✅ PHP dashboards run on Railway
- ✅ Static frontend on Firebase
- ✅ API on Vercel
- ✅ All connect to Neon database

**Architecture:**
```
Firebase Hosting (Frontend) → Vercel API → Neon Database
Railway (PHP Dashboards) → Neon Database (direct)
```

### **Option 2: Build Frontend that Uses API** (Recommended for Firebase)
- ✅ Convert PHP dashboards to use your Node.js API
- ✅ Build static frontend (React, Vue, or vanilla JS)
- ✅ Deploy frontend to Firebase
- ✅ Everything goes through your API

---

## 🎯 **Final Setup:**

### **What Runs Where:**

1. **Firebase Hosting:**
   - Static frontend files (HTML, CSS, JS)
   - Calls your Vercel API
   - URL: `https://onlinebizpermit.web.app`

2. **Vercel:**
   - Node.js Backend API
   - `/api/*` routes
   - URL: `https://online-biz-permit.vercel.app/api/*`

3. **Railway (Optional):**
   - PHP Dashboards (if you keep them)
   - URL: `https://your-app.railway.app/*`

4. **Neon Database:**
   - Shared database for all services

---

## ✅ **Summary:**

- ✅ **Backend API:** Vercel (already deployed)
- ✅ **Frontend:** Firebase Hosting (needs setup)
- ⚠️ **PHP Dashboards:** Railway or convert to use API
- ✅ **Database:** Neon (connected)

---

## 🚀 **Next Steps:**

1. **Create `public` folder** with your frontend files
2. **Update `firebase.json`** configuration
3. **Run `firebase deploy --only hosting`**
4. **Test your frontend** at Firebase URL
5. **Verify API calls** work from frontend

**Ready to set up Firebase Hosting?** Let me know and I'll help you create the frontend files! 🎉

