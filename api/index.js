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

// CORS configuration
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

const corsOptions = {
  origin: (origin, callback) => {
    !origin || allowedOrigins.includes(origin) ? callback(null, true) : callback(new Error('Not allowed by CORS'));
  },
  credentials: true,
  methods: ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS', 'PATCH'],
  allowedHeaders: ['Content-Type', 'Authorization', 'X-Requested-With', 'Accept', 'Origin'],
  exposedHeaders: ['Content-Length', 'Content-Type'],
  optionsSuccessStatus: 200
};

// Apply CORS middleware FIRST, before any other middleware
app.use(cors(corsOptions));

// Handle preflight requests
app.options('*', cors(corsOptions));

// --- START DEBUGGING MIDDLEWARE ---
// This will log every request that hits the Express app
app.use((req, res, next) => {
  console.log(`[INCOMING REQUEST] ${new Date().toISOString()}`);
  console.log(`  METHOD: ${req.method}`);
  console.log(`  URL: ${req.url}`);
  console.log(`  ORIGINAL_URL: ${req.originalUrl}`);
  console.log(`  HEADERS: ${JSON.stringify(req.headers, null, 2)}`);
  // Note: req.body is only populated after the express.json() middleware runs.
  // We will log it inside the 404 handler for a complete picture if a route is not found.
  next();
});
// --- END DEBUGGING MIDDLEWARE ---

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

// Create a new router for our API
const apiRouter = express.Router();

// Applicant Login endpoint
apiRouter.post('/auth/login', async (req, res) => {
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
apiRouter.post('/auth/staff-login', async (req, res) => {
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
apiRouter.post('/auth/signup', async (req, res) => {
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
apiRouter.get('/users/dashboard', authenticateToken, async (req, res) => {
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
apiRouter.get('/health', (req, res) => {
  console.log('Health check hit:', req.url, req.method);
  res.json({ status: 'OK', timestamp: new Date().toISOString() });
});

// Root route for testing
apiRouter.get('/', (req, res) => {
  console.log('Root route hit:', req.url, req.method);
  res.json({ 
    message: 'API is working!',
    routes: ['/auth/login', '/auth/staff-login', '/auth/signup', '/health']
  });
});

// Mount the API router. Since Vercel routes /api/* to this file,
// we mount the router at the root '/'.
app.use('/', apiRouter);

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
  console.error('--- 404: ROUTE NOT FOUND ---');
  console.error(`  Timestamp: ${new Date().toISOString()}`);
  console.error(`  Method: ${req.method}`);
  console.error(`  URL (as seen by Express): ${req.url}`);
  console.error(`  Original URL: ${req.originalUrl}`);
  if (req.body && Object.keys(req.body).length > 0) {
    console.error(`  Request Body: ${JSON.stringify(req.body, null, 2)}`);
  }
  res.status(404).json({
    success: false,
    message: 'Endpoint not found',
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
