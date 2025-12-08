// Only load dotenv in local development (Vercel provides env vars automatically)
if (process.env.NODE_ENV !== 'production' && !process.env.VERCEL) {
  try {
    require('dotenv').config();
  } catch (e) {
    // dotenv not available, continue without it
  }
}
const express = require('express');
const cors = require('cors');
const { Pool } = require('pg');
const bcrypt = require('bcryptjs');
const jwt = require('jsonwebtoken');
const nodemailer = require('nodemailer');

const app = express();
const PORT = process.env.PORT || 3000;

// Middleware - CORS configuration
const allowedOrigins = [
  // Default Firebase domains
  'https://onlinebizpermit.web.app',
  'https://onlinebizpermit.firebaseapp.com',
  // Firebase dashboard domains
  'https://admin-dashboardbiz.web.app',
  'https://applicant-dashboardbiz.web.app',
  'https://staff-dashboardbiz.web.app',
  // Vercel deployment domains (will be set via environment)
  process.env.VERCEL_URL ? `https://${process.env.VERCEL_URL}` : null,
  process.env.VERCEL_BRANCH_URL ? `https://${process.env.VERCEL_BRANCH_URL}` : null,
  // Local development
  'http://localhost:5000',
  'http://localhost:3000',
  'http://127.0.0.1:5500' // For local testing with Live Server
].filter(Boolean); // Remove null values

// CORS configuration
const corsOptions = {
  origin: function (origin, callback) {
    // Allow requests with no origin (like mobile apps or curl requests)
    if (!origin) {
      return callback(null, true);
    }
    
    // Check if origin is in allowed list
    if (allowedOrigins.includes(origin)) {
      callback(null, true);
    } else {
      // Log for debugging
      console.log('CORS: Blocked origin:', origin);
      console.log('CORS: Allowed origins:', allowedOrigins);
      callback(new Error('Not allowed by CORS'));
    }
  },
  credentials: true,
  methods: ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS', 'PATCH'],
  allowedHeaders: ['Content-Type', 'Authorization', 'X-Requested-With', 'Accept', 'Origin'],
  exposedHeaders: ['Content-Length', 'Content-Type'],
  preflightContinue: false,
  optionsSuccessStatus: 204,
  maxAge: 86400 // 24 hours
};

// Apply CORS middleware FIRST, before any other middleware
app.use(cors(corsOptions));

// Explicitly handle OPTIONS preflight requests for all routes
app.options('*', cors(corsOptions));
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// Initialize PostgreSQL connection (Neon)
const pool = new Pool({
  connectionString: process.env.DATABASE_URL,
  ssl: process.env.DATABASE_URL?.includes('sslmode=require') ? { rejectUnauthorized: false } : false
});

// Test database connection
pool.on('connect', () => {
  console.log('Connected to PostgreSQL database');
});

pool.on('error', (err) => {
  console.error('Unexpected error on idle client', err);
  process.exit(-1);
});

// Email configuration
const emailTransporter = nodemailer.createTransport({
  host: process.env.SMTP_HOST,
  port: process.env.SMTP_PORT || 587,
  secure: false,
  auth: {
    user: process.env.SMTP_USER,
    pass: process.env.SMTP_PASS
  }
});

// Authentication middleware
const authenticateToken = (req, res, next) => {
  const authHeader = req.headers['authorization'];
  const token = authHeader && authHeader.split(' ')[1];

  if (!token) return res.status(401).json({ success: false, message: 'Access token required' });

  jwt.verify(token, process.env.JWT_SECRET || 'your-secret-key', (err, user) => {
    if (err) return res.status(403).json({ success: false, message: 'Invalid token' });
    req.user = user;
    next();
  });
};

// Routes

// Root route - serve landing page
// In Vercel, this function is mounted at /api, so '/' here becomes /api
app.get('/', (req, res) => {
  console.log('Root route hit:', req.url, req.method);
  const landingPageHTML = `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Business Permit System</title>
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 40px;
            text-align: center;
            max-width: 500px;
            width: 90%;
        }
        .logo { font-size: 3rem; color: #667eea; margin-bottom: 20px; }
        h1 { color: #333; margin-bottom: 10px; font-size: 2rem; }
        p { color: #666; margin-bottom: 30px; font-size: 1.1rem; }
        .dashboard-grid { display: grid; gap: 20px; margin-top: 30px; }
        .dashboard-card {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 20px;
            text-decoration: none;
            color: #333;
            transition: all 0.3s ease;
            display: block;
        }
        .dashboard-card:hover {
            background: #667eea;
            color: white;
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }
        .dashboard-card h3 { margin: 0 0 8px 0; font-size: 1.3rem; }
        .dashboard-card p { margin: 0; font-size: 0.95rem; opacity: 0.8; }
        .dashboard-card:hover p { opacity: 1; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #e9ecef; color: #666; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">🏢</div>
        <h1>Online Business Permit System</h1>
        <p>Select your dashboard to get started</p>
        <div class="dashboard-grid">
            <a href="/Applicant-dashboard/index.php" class="dashboard-card">
                <h3>👤 Applicant Portal</h3>
                <p>Apply for business permits, track applications, and manage your submissions</p>
            </a>
            <a href="/Staff-dashboard/index.php" class="dashboard-card">
                <h3>👨‍💼 Staff Dashboard</h3>
                <p>Review applications, process permits, and manage workflow</p>
            </a>
            <a href="/Admin-dashboard/index.php" class="dashboard-card">
                <h3>⚡ Admin Panel</h3>
                <p>System administration, user management, and analytics</p>
            </a>
        </div>
        <div class="footer">
            <p>Powered by Firebase & Vercel</p>
        </div>
    </div>
</body>
</html>`;
  res.send(landingPageHTML);
});

// Applicant Login endpoint
app.post('/auth/login', async (req, res) => {
  try {
    const { email, password } = req.body;

    if (!email || !password) {
      return res.status(400).json({
        success: false,
        message: 'Email and password are required'
      });
    }

    // Query PostgreSQL for user by email
    const result = await pool.query(
      'SELECT id, name, email, password, role, is_approved FROM users WHERE email = $1 LIMIT 1',
      [email]
    );

    if (result.rows.length === 0) {
      // Timing attack protection - hash dummy password
      await bcrypt.hash('dummy', 10);
      return res.status(401).json({
        success: false,
        message: 'Invalid email or password'
      });
    }

    const user = result.rows[0];

    if (!(await bcrypt.compare(password, user.password))) {
      return res.status(401).json({
        success: false,
        message: 'Invalid email or password'
      });
    }

    if (user.role !== 'user') {
      return res.status(403).json({
        success: false,
        message: 'This login is for applicants only'
      });
    }

    if (user.is_approved === 0) {
      return res.status(403).json({
        success: false,
        message: 'Your account is pending admin approval'
      });
    }

    if (user.is_approved !== 1) {
      return res.status(403).json({
        success: false,
        message: 'Your account has been rejected'
      });
    }

    const token = jwt.sign(
      { userId: user.id, email: user.email, role: user.role },
      process.env.JWT_SECRET || 'your-secret-key',
      { expiresIn: '24h' }
    );

    res.json({
      success: true,
      message: 'Login successful',
      data: {
        token,
        user: {
          id: user.id,
          name: user.name,
          email: user.email,
          role: user.role
        }
      }
    });

  } catch (error) {
    console.error('Applicant login error:', error);
    res.status(500).json({
      success: false,
      message: 'Database error occurred'
    });
  }
});

// Staff/Admin Login endpoint
app.post('/auth/staff-login', async (req, res) => {
  console.log('Staff login route hit:', req.url, req.method, req.body);
  try {
    const { email, password } = req.body;

    if (!email || !password) {
      return res.status(400).json({
        success: false,
        message: 'Email and password are required'
      });
    }

    // Query PostgreSQL for staff/admin user by email
    const result = await pool.query(
      'SELECT id, name, email, password, role FROM users WHERE email = $1 AND role IN ($2, $3) LIMIT 1',
      [email, 'staff', 'admin']
    );

    if (result.rows.length === 0) {
      return res.status(401).json({
        success: false,
        message: 'Invalid email or password'
      });
    }

    const user = result.rows[0];

    if (!(await bcrypt.compare(password, user.password))) {
      return res.status(401).json({
        success: false,
        message: 'Invalid email or password'
      });
    }

    const token = jwt.sign(
      { userId: user.id, email: user.email, role: user.role },
      process.env.JWT_SECRET || 'your-secret-key',
      { expiresIn: '24h' }
    );

    res.json({
      success: true,
      message: 'Login successful',
      data: {
        token,
        user: {
          id: user.id,
          name: user.name,
          email: user.email,
          role: user.role
        },
        redirect: user.role === 'admin' ? '/Admin-dashboard/dashboard.php' : '/Staff-dashboard/dashboard.php'
      }
    });

  } catch (error) {
    console.error('Staff login error:', error);
    res.status(500).json({
      success: false,
      message: 'Database error occurred'
    });
  }
});

// Signup endpoint
app.post('/auth/signup', async (req, res) => {
  try {
    const { name, email, password } = req.body;

    if (!name || !email || !password) {
      return res.status(400).json({
        success: false,
        message: 'Name, email, and password are required'
      });
    }

    // Check if user already exists
    const existingUser = await pool.query(
      'SELECT id FROM users WHERE email = $1',
      [email]
    );

    if (existingUser.rows.length > 0) {
      return res.status(409).json({
        success: false,
        message: 'User already exists with this email'
      });
    }

    // Hash password
    const hashedPassword = await bcrypt.hash(password, 10);

    // Create new user
    const result = await pool.query(
      'INSERT INTO users (name, email, password, role, is_approved) VALUES ($1, $2, $3, $4, $5) RETURNING id',
      [name, email, hashedPassword, 'user', 0]
    );

    res.status(201).json({
      success: true,
      message: 'Registration successful! Please wait for admin approval.',
      data: { userId: result.rows[0].id }
    });

  } catch (error) {
    console.error('Signup error:', error);
    res.status(500).json({
      success: false,
      message: 'Registration failed'
    });
  }
});

// Dashboard endpoint
app.get('/users/dashboard', authenticateToken, async (req, res) => {
  try {
    const userId = req.user.userId;

    // Get user info from PostgreSQL
    const userResult = await pool.query(
      'SELECT id, name, email FROM users WHERE id = $1',
      [userId]
    );

    if (userResult.rows.length === 0) {
      return res.status(404).json({
        success: false,
        message: 'User not found'
      });
    }
    const user = userResult.rows[0];

    // Get user applications from PostgreSQL
    const applicationsResult = await pool.query(
      'SELECT * FROM applications WHERE user_id = $1 ORDER BY submitted_at DESC',
      [userId]
    );

    const applications = applicationsResult.rows;

    // Get user notifications from PostgreSQL
    const notificationsResult = await pool.query(
      'SELECT * FROM notifications WHERE user_id = $1 ORDER BY created_at DESC LIMIT 5',
      [userId]
    );

    const notifications = notificationsResult.rows;

    res.json({
      success: true,
      data: {
        user: {
          id: user.id,
          name: user.name,
          email: user.email
        },
        applications,
        notifications
      }
    });

  } catch (error) {
    console.error('Dashboard error:', error);
    res.status(500).json({
      success: false,
      message: 'Failed to load dashboard'
    });
  }
});

// Health check
app.get('/health', (req, res) => {
  console.log('Health check hit:', req.url, req.method);
  res.json({ status: 'OK', timestamp: new Date().toISOString() });
});

// Error handling middleware
app.use((err, req, res, next) => {
  console.error(err.stack);
  res.status(500).json({
    success: false,
    message: 'Something went wrong!'
  });
});

// 404 handler - log all unmatched routes for debugging
app.use('*', (req, res) => {
  console.log('404 - Route not found:', req.method, req.url, 'Original URL:', req.originalUrl);
  res.status(404).json({
    success: false,
    message: 'Endpoint not found',
    method: req.method,
    url: req.url,
    originalUrl: req.originalUrl
  });
});

// For local development, start the server
if (require.main === module) {
  app.listen(PORT, () => {
    console.log(`Server running on port ${PORT}`);
  });
}

// Export for Vercel serverless functions
// Vercel automatically detects api/index.js and mounts it at /api
// Routes should be relative (e.g., '/auth/login' not '/api/auth/login')
module.exports = app;
