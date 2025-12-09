#!/usr/bin/env node
/**
 * Script to set up public directory structure for Firebase hosting
 */

const fs = require('fs');
const path = require('path');

const publicDir = path.join(__dirname, 'public');
const dashboards = ['admin', 'applicant', 'staff'];
const subdirs = ['css', 'js', 'images'];

console.log('🚀 Setting up Firebase hosting directories...\n');

// Create main public directory if it doesn't exist
if (!fs.existsSync(publicDir)) {
  fs.mkdirSync(publicDir, { recursive: true });
  console.log('✅ Created public/ directory');
}

// Create directories for each dashboard
dashboards.forEach(dashboard => {
  const dashboardDir = path.join(publicDir, dashboard);
  
  if (!fs.existsSync(dashboardDir)) {
    fs.mkdirSync(dashboardDir, { recursive: true });
    console.log(`✅ Created public/${dashboard}/`);
  }
  
  // Create subdirectories
  subdirs.forEach(subdir => {
    const subdirPath = path.join(dashboardDir, subdir);
    if (!fs.existsSync(subdirPath)) {
      fs.mkdirSync(subdirPath, { recursive: true });
      console.log(`   ✅ Created public/${dashboard}/${subdir}/`);
    }
  });
  
  // Create a placeholder index.html
  const indexPath = path.join(dashboardDir, 'index.html');
  if (!fs.existsSync(indexPath)) {
    const placeholderHTML = `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>${dashboard.charAt(0).toUpperCase() + dashboard.slice(1)} Dashboard</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .container {
            text-align: center;
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        h1 { color: #333; margin-bottom: 20px; }
        p { color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <h1>${dashboard.charAt(0).toUpperCase() + dashboard.slice(1)} Dashboard</h1>
        <p>This is a placeholder. Replace this with your converted HTML/JS files.</p>
        <p><strong>Note:</strong> Convert your PHP files to static HTML/JavaScript that calls your Vercel API.</p>
    </div>
</body>
</html>`;
    fs.writeFileSync(indexPath, placeholderHTML);
    console.log(`   ✅ Created placeholder public/${dashboard}/index.html`);
  }
});

// Create config.js template
const configPath = path.join(publicDir, 'config.js');
if (!fs.existsSync(configPath)) {
  const configTemplate = `// Frontend Configuration
// Update this with your actual Vercel backend URL after deployment

const CONFIG = {
  // Your Vercel backend URL (update after deployment)
  API_BASE_URL: 'https://your-vercel-app.vercel.app/api',
  
  // Firebase Configuration
  FIREBASE_CONFIG: {
    apiKey: "AIzaSyDPZY7B1BKzNrJRTulWFa0P0t28qlMDzig",
    authDomain: "onlinebizpermit.firebaseapp.com",
    projectId: "onlinebizpermit",
    storageBucket: "onlinebizpermit.firebasestorage.app",
    messagingSenderId: "37215767726",
    appId: "1:37215767726:web:44e68cd75b2628b438b13f",
    measurementId: "G-7RJHQKV7SC"
  }
};

// Make it available globally
if (typeof window !== 'undefined') {
  window.CONFIG = CONFIG;
}

// For Node.js environments
if (typeof module !== 'undefined' && module.exports) {
  module.exports = CONFIG;
}`;
  fs.writeFileSync(configPath, configTemplate);
  console.log('✅ Created public/config.js template');
}

console.log('\n✨ Directory structure created!');
console.log('\n📝 Next steps:');
console.log('1. Convert your PHP files to HTML/JavaScript');
console.log('2. Copy CSS, JS, and image files to the respective directories');
console.log('3. Update API calls to use your Vercel backend URL');
console.log('4. Run: firebase deploy --only hosting\n');

