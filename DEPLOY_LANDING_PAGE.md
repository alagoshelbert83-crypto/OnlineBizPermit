# 🚀 Deploy Landing Page to Firebase

## ✅ **Your Setup:**

You have separate Firebase sites:
- ✅ **Applicant Dashboard:** https://applicant-dashboardbiz.web.app/
- ✅ **Staff Dashboard:** https://staff-dashboardbiz.web.app/
- ✅ **Admin Dashboard:** https://admin-dashboardbiz.web.app/
- ⚠️ **Landing Page:** Needs to be deployed

---

## 📋 **Steps to Deploy Landing Page:**

### **Step 1: Verify Your Landing Page**

I've updated `public/index.html` to link to your Firebase-hosted dashboards:
- ✅ Links to `applicant-dashboardbiz.web.app`
- ✅ Links to `staff-dashboardbiz.web.app`
- ✅ Links to `admin-dashboardbiz.web.app`

### **Step 2: Deploy to Main Firebase Project**

Deploy the landing page to your main Firebase project (`onlinebizpermit`):

```bash
firebase deploy --only hosting
```

This will deploy to your main site: `https://onlinebizpermit.web.app`

---

## 🎯 **Alternative: Deploy to Specific Site**

If you want to deploy to a different Firebase hosting site:

```bash
# List your sites
firebase hosting:sites:list

# Deploy to a specific site
firebase target:apply hosting landing-page <site-id>
firebase deploy --only hosting:landing-page
```

---

## ✅ **After Deployment:**

Your landing page will be available at:
- **Main site:** `https://onlinebizpermit.web.app`
- Or whichever site you deployed to

The landing page will have links to all three dashboards:
- 👤 Applicant Portal → https://applicant-dashboardbiz.web.app/
- 👨‍💼 Staff Dashboard → https://staff-dashboardbiz.web.app/
- ⚡ Admin Panel → https://admin-dashboardbiz.web.app/

---

## 🔄 **Update firebase.json (if needed)**

If you have multiple Firebase hosting sites, your `firebase.json` should look like:

```json
{
  "hosting": [
    {
      "target": "landing",
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
  ]
}
```

---

## 🚀 **Quick Deploy:**

Just run:
```bash
firebase deploy --only hosting
```

Your landing page is ready! 🎉

