#!/usr/bin/env node
/**
 * Helper script to extract Firebase credentials from JSON file
 * and format them for Vercel environment variables
 */

const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

// Look for any Firebase service account JSON file
const jsonFiles = fs.readdirSync(__dirname).filter(file => 
  file.endsWith('.json') && 
  file.includes('firebase-adminsdk') && 
  file !== 'package.json' && 
  file !== 'package-lock.json' &&
  file !== 'composer.json'
);

const jsonPath = jsonFiles.length > 0 
  ? path.join(__dirname, jsonFiles[0])
  : path.join(__dirname, 'onlinebizpermit-firebase-adminsdk-fbsvc-2eb6871142.json');

if (!fs.existsSync(jsonPath)) {
  console.error('❌ Firebase service account JSON file not found!');
  console.error('   Expected location:', jsonPath);
  console.error('\n💡 To generate a new service account key:');
  console.error('   1. Go to Firebase Console → Project Settings → Service Accounts');
  console.error('   2. Click "Generate new private key"');
  console.error('   3. Download the JSON file to this directory');
  console.error('   4. Run this script again\n');
  process.exit(1);
}

try {
  const serviceAccount = JSON.parse(fs.readFileSync(jsonPath, 'utf8'));
  
  console.log('\n✅ Firebase Service Account Credentials Extracted\n');
  console.log('='.repeat(60));
  console.log('Copy these to Vercel → Settings → Environment Variables\n');
  
  console.log('FIREBASE_PRIVATE_KEY_ID');
  console.log('─'.repeat(60));
  console.log(serviceAccount.private_key_id);
  console.log();
  
  console.log('FIREBASE_PRIVATE_KEY');
  console.log('─'.repeat(60));
  console.log('(Keep the \\n characters as shown)');
  console.log(serviceAccount.private_key);
  console.log();
  
  console.log('FIREBASE_CLIENT_EMAIL');
  console.log('─'.repeat(60));
  console.log(serviceAccount.client_email);
  console.log();
  
  console.log('FIREBASE_CLIENT_ID');
  console.log('─'.repeat(60));
  console.log(serviceAccount.client_id);
  console.log();
  
  console.log('FIREBASE_CLIENT_X509_CERT_URL');
  console.log('─'.repeat(60));
  console.log(serviceAccount.client_x509_cert_url);
  console.log();
  
  console.log('='.repeat(60));
  console.log('\n📝 Additional Variables You Need to Set:\n');
  
  console.log('JWT_SECRET');
  console.log('─'.repeat(60));
  const jwtSecret = crypto.randomBytes(32).toString('hex');
  console.log(jwtSecret);
  console.log('(Generated a random secret for you - use this or generate your own)');
  console.log();
  
  console.log('SMTP_HOST (optional - for email)');
  console.log('─'.repeat(60));
  console.log('smtp.gmail.com');
  console.log();
  
  console.log('SMTP_PORT (optional - for email)');
  console.log('─'.repeat(60));
  console.log('587');
  console.log();
  
  console.log('SMTP_USER (optional - your email)');
  console.log('─'.repeat(60));
  console.log('your-email@gmail.com');
  console.log();
  
  console.log('SMTP_PASS (optional - Gmail app password)');
  console.log('─'.repeat(60));
  console.log('your-app-password');
  console.log();
  
  console.log('='.repeat(60));
  console.log('\n✨ Next Steps:');
  console.log('1. Go to Vercel Dashboard → Your Project → Settings → Environment Variables');
  console.log('2. Add each variable above');
  console.log('3. Redeploy your project');
  console.log('4. Test: https://your-app.vercel.app/api/health\n');
  
} catch (error) {
  console.error('❌ Error reading JSON file:', error.message);
  process.exit(1);
}

