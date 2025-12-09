require('dotenv').config();
const express = require('express');
const cors = require('cors');
const admin = require('firebase-admin');
const bcrypt = require('bcryptjs');
const jwt = require('jsonwebtoken');
const nodemailer = require('nodemailer');
const { Pool } = require('pg');

const app = express();
const PORT = process.env.PORT || 3000;

// Middleware
app.use(cors({
  origin: [
    'https://onlinebizpermit.web.app',
    'https://onlinebizpermit.firebaseapp.com',
    'http://localhost:3000',
    'http://localhost:5000',
    process.env.FRONTEND_URL // Allow custom frontend URL
  ].filter(Boolean),
  credentials: true
}));
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// Initialize Firebase Admin
const serviceAccount = {
  type: "service_account",
  project_id: "onlinebizpermit",
  private_key_id: process.env.FIREBASE_PRIVATE_KEY_ID,
  private_key: process.env.FIREBASE_PRIVATE_KEY?.replace(/\\n/g, '\n'),
  client_email: process.env.FIREBASE_CLIENT_EMAIL,
  client_id: process.env.FIREBASE_CLIENT_ID,
  auth_uri: "https://accounts.google.com/o/oauth2/auth",
  token_uri: "https://oauth2.googleapis.com/token",
  auth_provider_x509_cert_url: "https://www.googleapis.com/oauth2/v1/certs",
  client_x509_cert_url: process.env.FIREBASE_CLIENT_X509_CERT_URL
};

admin.initializeApp({
  credential: admin.credential.cert(serviceAccount),
  databaseURL: `https://onlinebizpermit.firebaseio.com`
});

const db = admin.firestore();

// PostgreSQL/Neon Database Connection
let pgPool = null;
try {
  const postgresUrl = process.env.DATABASE_POSTGRES_URL || process.env.DATABASE_URL || process.env.POSTGRES_URL;
  if (postgresUrl) {
    pgPool = new Pool({
      connectionString: postgresUrl,
      ssl: {
        rejectUnauthorized: false // Required for Neon
      }
    });
    console.log('✅ PostgreSQL (Neon) connection pool initialized');
  } else {
    console.log('⚠️  No PostgreSQL connection string found');
  }
} catch (error) {
  console.error('❌ Failed to initialize PostgreSQL pool:', error.message);
}

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
app.get('/', (req, res) => {
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
app.post('/api/auth/login', async (req, res) => {
  try {
    const { email, password } = req.body;

    if (!email || !password) {
      return res.status(400).json({
        success: false,
        message: 'Email and password are required'
      });
    }

    // Query Firestore for user by email
    const usersRef = db.collection('users');
    const querySnapshot = await usersRef.where('email', '==', email).limit(1).get();

    if (querySnapshot.empty) {
      return res.status(401).json({
        success: false,
        message: 'Invalid email or password'
      });
    }

    const userDoc = querySnapshot.docs[0];
    const user = { id: userDoc.id, ...userDoc.data() };

    // Timing attack protection
    const passwordHash = user ? user.password : await bcrypt.hash('dummy', 10);

    if (!(await bcrypt.compare(password, passwordHash))) {
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
app.post('/api/auth/staff-login', async (req, res) => {
  try {
    const { email, password } = req.body;

    if (!email || !password) {
      return res.status(400).json({
        success: false,
        message: 'Email and password are required'
      });
    }

    // Query Firestore for staff/admin user by email
    const usersRef = db.collection('users');
    const querySnapshot = await usersRef.where('email', '==', email).where('role', 'in', ['staff', 'admin']).limit(1).get();

    if (querySnapshot.empty) {
      return res.status(401).json({
        success: false,
        message: 'Invalid email or password'
      });
    }

    const userDoc = querySnapshot.docs[0];
    const user = { id: userDoc.id, ...userDoc.data() };

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
app.post('/api/auth/signup', async (req, res) => {
  try {
    const { name, email, password } = req.body;

    if (!name || !email || !password) {
      return res.status(400).json({
        success: false,
        message: 'Name, email, and password are required'
      });
    }

    // Check if user already exists
    const usersRef = db.collection('users');
    const existingQuery = await usersRef.where('email', '==', email).limit(1).get();

    if (!existingQuery.empty) {
      return res.status(409).json({
        success: false,
        message: 'User already exists with this email'
      });
    }

    // Hash password
    const hashedPassword = await bcrypt.hash(password, 10);

    // Create new user document
    const newUser = {
      name,
      email,
      password: hashedPassword,
      role: 'user',
      is_approved: 0,
      created_at: admin.firestore.FieldValue.serverTimestamp()
    };

    const docRef = await usersRef.add(newUser);

    res.status(201).json({
      success: true,
      message: 'Registration successful! Please wait for admin approval.',
      data: { userId: docRef.id }
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
app.get('/api/users/dashboard', authenticateToken, async (req, res) => {
  try {
    const userId = req.user.userId;

    // Get user info from Firestore
    const userDoc = await db.collection('users').doc(userId).get();
    if (!userDoc.exists) {
      return res.status(404).json({
        success: false,
        message: 'User not found'
      });
    }
    const user = { id: userDoc.id, ...userDoc.data() };

    // Get user applications from Firestore
    const applicationsSnapshot = await db.collection('applications')
      .where('user_id', '==', userId)
      .orderBy('submitted_at', 'desc')
      .get();

    const applications = applicationsSnapshot.docs.map(doc => ({
      id: doc.id,
      ...doc.data()
    }));

    // Get user notifications from Firestore
    const notificationsSnapshot = await db.collection('notifications')
      .where('user_id', '==', userId)
      .orderBy('created_at', 'desc')
      .limit(5)
      .get();

    const notifications = notificationsSnapshot.docs.map(doc => ({
      id: doc.id,
      ...doc.data()
    }));

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

// Health check - support both /api/health and /health
app.get('/api/health', (req, res) => {
  res.json({ status: 'OK', timestamp: new Date().toISOString() });
});

app.get('/health', (req, res) => {
  res.json({ status: 'OK', timestamp: new Date().toISOString() });
});

// Test Neon PostgreSQL Connection
app.get('/api/test/db', async (req, res) => {
  try {
    if (!pgPool) {
      return res.status(503).json({
        success: false,
        message: 'PostgreSQL connection not configured',
        error: 'DATABASE_POSTGRES_URL or DATABASE_URL environment variable not found'
      });
    }

    // Test connection
    const client = await pgPool.connect();
    
    // Run a simple query
    const result = await client.query('SELECT NOW() as current_time, version() as pg_version');
    
    // Check if users table exists
    const tableCheck = await client.query(`
      SELECT EXISTS (
        SELECT FROM information_schema.tables 
        WHERE table_schema = 'public' 
        AND table_name = 'users'
      );
    `);

    // Get table count if exists
    let tableCount = null;
    if (tableCheck.rows[0].exists) {
      const countResult = await client.query('SELECT COUNT(*) as count FROM users');
      tableCount = parseInt(countResult.rows[0].count);
    }

    client.release();

    res.json({
      success: true,
      message: '✅ Database connection successful!',
      data: {
        connection: 'Connected to Neon PostgreSQL',
        currentTime: result.rows[0].current_time,
        postgresVersion: result.rows[0].pg_version.split(',')[0], // First line only
        usersTableExists: tableCheck.rows[0].exists,
        usersCount: tableCount,
        environment: {
          hasPostgresUrl: !!process.env.DATABASE_POSTGRES_URL,
          hasDatabaseUrl: !!process.env.DATABASE_URL,
          hasPostgresUrlAlt: !!process.env.POSTGRES_URL
        }
      },
      timestamp: new Date().toISOString()
    });

  } catch (error) {
    console.error('Database test error:', error);
    res.status(500).json({
      success: false,
      message: '❌ Database connection failed',
      error: error.message,
      details: {
        code: error.code,
        hint: error.hint || null
      }
    });
  }
});

// Test Neon PostgreSQL - List Tables
app.get('/api/test/db/tables', async (req, res) => {
  try {
    if (!pgPool) {
      return res.status(503).json({
        success: false,
        message: 'PostgreSQL connection not configured'
      });
    }

    const client = await pgPool.connect();
    
    // Get all tables
    const result = await client.query(`
      SELECT table_name, 
             (SELECT COUNT(*) FROM information_schema.columns WHERE table_name = t.table_name) as column_count
      FROM information_schema.tables t
      WHERE table_schema = 'public' 
      AND table_type = 'BASE TABLE'
      ORDER BY table_name;
    `);

    client.release();

    res.json({
      success: true,
      message: `Found ${result.rows.length} table(s)`,
      data: {
        tables: result.rows,
        count: result.rows.length
      }
    });

  } catch (error) {
    console.error('Database tables error:', error);
    res.status(500).json({
      success: false,
      message: 'Failed to list tables',
      error: error.message
    });
  }
});

// Error handling middleware
app.use((err, req, res, next) => {
  console.error(err.stack);
  res.status(500).json({
    success: false,
    message: 'Something went wrong!'
  });
});

// 404 handler
app.use('*', (req, res) => {
  res.status(404).json({
    success: false,
    message: 'Endpoint not found'
  });
});

app.listen(PORT, () => {
  console.log(`Server running on port ${PORT}`);
});

module.exports = app;
