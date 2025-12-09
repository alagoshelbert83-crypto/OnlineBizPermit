// Frontend Configuration
// Update this with your actual Vercel backend URL after deployment

const CONFIG = {
  // Your Vercel backend URL (update after deployment)
  API_BASE_URL: 'https://online-biz-permit.vercel.app/api',
  
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
}

