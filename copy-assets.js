#!/usr/bin/env node
/**
 * Script to copy static assets (CSS, images, JS) from PHP dashboards to public directories
 */

const fs = require('fs');
const path = require('path');

console.log('📦 Copying static assets to public directories...\n');

const mappings = [
  {
    source: 'Admin-dashboard',
    target: 'public/admin',
    name: 'Admin Dashboard'
  },
  {
    source: 'Applicant-dashboard',
    target: 'public/applicant',
    name: 'Applicant Dashboard'
  },
  {
    source: 'Staff-dashboard',
    target: 'public/staff',
    name: 'Staff Dashboard'
  }
];

function copyFile(src, dest) {
  try {
    const destDir = path.dirname(dest);
    if (!fs.existsSync(destDir)) {
      fs.mkdirSync(destDir, { recursive: true });
    }
    fs.copyFileSync(src, dest);
    return true;
  } catch (error) {
    return false;
  }
}

mappings.forEach(({ source, target, name }) => {
  const sourceDir = path.join(__dirname, source);
  const targetDir = path.join(__dirname, target);
  
  if (!fs.existsSync(sourceDir)) {
    console.log(`⚠️  ${name}: Source directory not found (${source})`);
    return;
  }
  
  console.log(`\n📁 ${name}:`);
  
  // Copy CSS files
  const cssFiles = fs.readdirSync(sourceDir).filter(f => f.endsWith('.css'));
  cssFiles.forEach(file => {
    const src = path.join(sourceDir, file);
    const dest = path.join(targetDir, 'css', file);
    if (copyFile(src, dest)) {
      console.log(`   ✅ Copied CSS: ${file}`);
    }
  });
  
  // Copy JS files
  const jsFiles = fs.readdirSync(sourceDir).filter(f => f.endsWith('.js'));
  jsFiles.forEach(file => {
    const src = path.join(sourceDir, file);
    const dest = path.join(targetDir, 'js', file);
    if (copyFile(src, dest)) {
      console.log(`   ✅ Copied JS: ${file}`);
    }
  });
  
  // Copy image files
  const imageFiles = fs.readdirSync(sourceDir).filter(f => 
    f.endsWith('.png') || f.endsWith('.jpg') || f.endsWith('.jpeg') || f.endsWith('.gif') || f.endsWith('.svg')
  );
  imageFiles.forEach(file => {
    const src = path.join(sourceDir, file);
    const dest = path.join(targetDir, 'images', file);
    if (copyFile(src, dest)) {
      console.log(`   ✅ Copied image: ${file}`);
    }
  });
});

console.log('\n✨ Asset copying complete!');
console.log('\n⚠️  Note: PHP files were NOT copied. You need to convert them to HTML/JavaScript.');
console.log('   - Replace PHP includes with JavaScript imports');
console.log('   - Replace PHP sessions with JWT tokens (localStorage)');
console.log('   - Replace database queries with API calls to your Vercel backend\n');

