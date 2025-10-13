const http = require('http');
const path = require('path');
const fs = require('fs');
const { URL } = require('url');
const crypto = require('crypto');

const PORT = process.env.PORT || 4000;
const HOST = process.env.HOST || '0.0.0.0';
const DATA_DIR = path.join(__dirname, 'data');
const USERS_FILE = path.join(DATA_DIR, 'users.json');
const STATIC_ROOT = path.join(__dirname, '..');

const sessions = new Map();

function ensureDataDirectory() {
  if (!fs.existsSync(DATA_DIR)) {
    fs.mkdirSync(DATA_DIR, { recursive: true });
  }
}

function createPasswordRecord(password) {
  const salt = crypto.randomBytes(16).toString('hex');
  const hash = crypto.pbkdf2Sync(password, salt, 100000, 64, 'sha512').toString('hex');
  return { salt, hash, iterations: 100000, algorithm: 'sha512' };
}

function verifyPassword(password, record) {
  if (!record) return false;
  const hash = crypto.pbkdf2Sync(password, record.salt, record.iterations, 64, record.algorithm).toString('hex');
  return crypto.timingSafeEqual(Buffer.from(hash, 'hex'), Buffer.from(record.hash, 'hex'));
}

function readUsers() {
  ensureDataDirectory();
  if (!fs.existsSync(USERS_FILE)) {
    const defaults = seedUsers([]);
    fs.writeFileSync(USERS_FILE, JSON.stringify(defaults, null, 2));
    return defaults;
  }
  const raw = fs.readFileSync(USERS_FILE, 'utf8');
  try {
    const parsed = JSON.parse(raw || '[]');
    const seeded = seedUsers(parsed);
    if (seeded.length !== parsed.length) {
      fs.writeFileSync(USERS_FILE, JSON.stringify(seeded, null, 2));
      return seeded;
    }
    return seeded;
  } catch (error) {
    console.error('Failed to parse users file, resetting.', error);
    const defaults = seedUsers([]);
    fs.writeFileSync(USERS_FILE, JSON.stringify(defaults, null, 2));
    return defaults;
  }
}

function writeUsers(users) {
  fs.writeFileSync(USERS_FILE, JSON.stringify(users, null, 2));
}

function seedUsers(existingUsers) {
  const users = Array.isArray(existingUsers) ? [...existingUsers] : [];
  const ensureUser = (email, creator) => {
    if (!users.some(user => user.email.toLowerCase() === email.toLowerCase())) {
      users.push(creator());
    }
  };

  ensureUser('admin@dakshayani.in', () => ({
    id: 'usr-admin-1',
    name: 'Dakshayani Admin',
    email: 'admin@dakshayani.in',
    phone: '+91 70000 00000',
    city: 'Ranchi',
    role: 'admin',
    password: createPasswordRecord('Admin@123'),
    createdAt: new Date().toISOString()
  }));

  ensureUser('customer@dakshayani.in', () => ({
    id: 'usr-customer-1',
    name: 'Asha Verma',
    email: 'customer@dakshayani.in',
    phone: '+91 90000 00000',
    city: 'Jamshedpur',
    role: 'customer',
    password: createPasswordRecord('Customer@123'),
    createdAt: new Date().toISOString()
  }));

  ensureUser('employee@dakshayani.in', () => ({
    id: 'usr-employee-1',
    name: 'Rohit Kumar',
    email: 'employee@dakshayani.in',
    phone: '+91 88000 00000',
    city: 'Bokaro',
    role: 'employee',
    password: createPasswordRecord('Employee@123'),
    createdAt: new Date().toISOString()
  }));

  ensureUser('installer@dakshayani.in', () => ({
    id: 'usr-installer-1',
    name: 'Sunita Singh',
    email: 'installer@dakshayani.in',
    phone: '+91 86000 00000',
    city: 'Dhanbad',
    role: 'installer',
    password: createPasswordRecord('Installer@123'),
    createdAt: new Date().toISOString()
  }));

  ensureUser('referrer@dakshayani.in', () => ({
    id: 'usr-referrer-1',
    name: 'Sanjay Patel',
    email: 'referrer@dakshayani.in',
    phone: '+91 94000 00000',
    city: 'Hazaribagh',
    role: 'referrer',
    password: createPasswordRecord('Referrer@123'),
    createdAt: new Date().toISOString()
  }));

  return users;
}

function sendJson(res, statusCode, payload) {
  const body = JSON.stringify(payload);
  res.writeHead(statusCode, {
    'Content-Type': 'application/json',
    'Content-Length': Buffer.byteLength(body),
    'Access-Control-Allow-Origin': '*',
    'Access-Control-Allow-Headers': 'Content-Type, Authorization',
    'Access-Control-Allow-Methods': 'GET, POST, OPTIONS'
  });
  res.end(body);
}

function sendNotFound(res) {
  res.writeHead(404, { 'Content-Type': 'text/plain' });
  res.end('Not found');
}

function collectRequestBody(req) {
  return new Promise((resolve, reject) => {
    const chunks = [];
    req.on('data', chunk => chunks.push(chunk));
    req.on('end', () => {
      try {
        const buffer = Buffer.concat(chunks);
        const text = buffer.toString('utf8') || '{}';
        resolve(text ? JSON.parse(text) : {});
      } catch (error) {
        reject(error);
      }
    });
    req.on('error', reject);
  });
}

function createToken(userId) {
  const token = crypto.randomBytes(32).toString('hex');
  sessions.set(token, { userId, createdAt: Date.now() });
  return token;
}

function getUserFromToken(req) {
  const auth = req.headers['authorization'];
  if (!auth) return null;
  const parts = auth.split(' ');
  if (parts.length !== 2 || parts[0] !== 'Bearer') return null;
  const token = parts[1];
  const session = sessions.get(token);
  if (!session) return null;
  const users = readUsers();
  return users.find(user => user.id === session.userId) || null;
}

function sanitizeUser(user) {
  if (!user) return null;
  const { password, ...safe } = user;
  return safe;
}

function buildDashboardData(user) {
  const base = {
    user: sanitizeUser(user),
    metrics: [],
    timeline: [],
    tasks: [],
    spotlight: {
      title: 'Stay proactive',
      message: 'Monitor your daily priorities and reach out if you need adjustments to these workflows.'
    }
  };

  switch (user.role) {
    case 'admin':
      base.metrics = [
        { label: 'Active Users', value: 128, helper: '12 added this month' },
        { label: 'Open Tickets', value: 9, helper: '3 high priority' },
        { label: 'Projects in Flight', value: 18, helper: '5 nearing completion' }
      ];
      base.timeline = [
        { label: 'Compliance audit', date: '2024-10-15', status: 'Scheduled' },
        { label: 'Quarterly board review', date: '2024-10-22', status: 'Planning' },
        { label: 'CRM data refresh', date: '2024-10-28', status: 'In Progress' }
      ];
      base.tasks = [
        { label: 'Approve installer onboarding requests', status: 'Pending' },
        { label: 'Review finance partner contracts', status: 'In Review' },
        { label: 'Publish monthly KPI summary', status: 'Due Friday' }
      ];
      base.spotlight = {
        title: 'System health is stable',
        message: 'All services are operational. Keep an eye on overdue approvals to maintain SLAs.'
      };
      break;
    case 'customer':
      base.metrics = [
        { label: 'System Progress', value: '80%', helper: 'Net metering approval in progress' },
        { label: 'Energy Saved (kWh)', value: 5120, helper: 'This billing cycle' },
        { label: 'Projected Savings', value: '₹14,200', helper: 'Estimated this quarter' }
      ];
      base.timeline = [
        { label: 'Site survey', date: '2024-09-30', status: 'Completed' },
        { label: 'Structural approval', date: '2024-10-10', status: 'Completed' },
        { label: 'Electrical inspection', date: '2024-10-18', status: 'Scheduled' }
      ];
      base.tasks = [
        { label: 'Upload recent electricity bill', status: 'Pending' },
        { label: 'Confirm access for installer team', status: 'Scheduled' },
        { label: 'Review financing documents', status: 'In Progress' }
      ];
      base.spotlight = {
        title: 'You are almost live!',
        message: 'Once the inspection is cleared we will schedule commissioning within 72 hours.'
      };
      break;
    case 'employee':
      base.metrics = [
        { label: 'Assigned Tickets', value: 24, helper: '5 due today' },
        { label: 'Customer CSAT', value: '4.7/5', helper: 'Rolling 30-day score' },
        { label: 'Pending Escalations', value: 2, helper: 'Awaiting regional lead input' }
      ];
      base.timeline = [
        { label: 'Installer coordination sync', date: '2024-10-11 10:00', status: 'Today' },
        { label: 'Customer success review', date: '2024-10-13 15:30', status: 'Scheduled' },
        { label: 'Knowledge base update', date: '2024-10-19', status: 'Drafting' }
      ];
      base.tasks = [
        { label: 'Call customer #DE-2041 regarding inspection', status: 'Pending' },
        { label: 'Update CRM notes for project JSR-118', status: 'In Progress' },
        { label: 'Submit weekly activity summary', status: 'Due Friday' }
      ];
      base.spotlight = {
        title: 'Customer sentiment is strong',
        message: 'Maintain quick response times to keep our customer satisfaction above target.'
      };
      break;
    case 'installer':
      base.metrics = [
        { label: 'Jobs This Week', value: 6, helper: '2 require structural clearance' },
        { label: 'Avg. Completion Time', value: '6.5 hrs', helper: 'Across active jobs' },
        { label: 'Safety Checks', value: '100%', helper: 'All audits submitted' }
      ];
      base.timeline = [
        { label: 'Ranchi - Verma Residence', date: '2024-10-11 09:00', status: 'Team A' },
        { label: 'Jamshedpur - Patel Industries', date: '2024-10-12 14:00', status: 'Team C' },
        { label: 'Bokaro - Singh Clinic', date: '2024-10-14 08:30', status: 'Team B' }
      ];
      base.tasks = [
        { label: 'Upload as-built photos for Ranchi site', status: 'Pending' },
        { label: 'Collect inverter serial numbers', status: 'In Progress' },
        { label: 'Confirm material delivery for Dhanbad project', status: 'Scheduled' }
      ];
      base.spotlight = {
        title: 'All materials accounted for',
        message: 'Warehouse reports zero shortages. Coordinate closely with logistics for on-time starts.'
      };
      break;
    case 'referrer':
      base.metrics = [
        { label: 'Active Leads', value: 14, helper: '4 new this week' },
        { label: 'Conversion Rate', value: '28%', helper: 'Trailing 90 days' },
        { label: 'Rewards Earned', value: '₹36,500', helper: 'Awaiting next payout cycle' }
      ];
      base.timeline = [
        { label: 'Lead #RF-882 follow-up', date: '2024-10-11', status: 'Call scheduled' },
        { label: 'Payout reconciliation', date: '2024-10-15', status: 'Processing' },
        { label: 'Referral webinar', date: '2024-10-20 17:00', status: 'Registration open' }
      ];
      base.tasks = [
        { label: 'Share site photos for lead #RF-876', status: 'Pending' },
        { label: 'Confirm bank details for rewards', status: 'In Progress' },
        { label: 'Invite 3 new prospects this week', status: 'Stretch goal' }
      ];
      base.spotlight = {
        title: 'Keep nurturing warm leads',
        message: 'Timely follow-ups and detailed context boost conversions. Reach out if you need marketing collateral.'
      };
      break;
    default:
      base.metrics = [
        { label: 'Active Items', value: 0, helper: 'No data yet' }
      ];
  }

  return base;
}

function handleApiRequest(req, res, url) {
  if (req.method === 'OPTIONS') {
    res.writeHead(204, {
      'Access-Control-Allow-Origin': '*',
      'Access-Control-Allow-Headers': 'Content-Type, Authorization',
      'Access-Control-Allow-Methods': 'GET, POST, OPTIONS'
    });
    res.end();
    return;
  }

  if (req.method === 'POST' && url.pathname === '/api/signup') {
    collectRequestBody(req)
      .then(body => {
        const { name, email, password, role = 'referrer', phone = '', city = '' } = body;
        if (!name || !email || !password) {
          sendJson(res, 400, { error: 'Name, email, and password are required.' });
          return;
        }
        const normalisedEmail = String(email).trim().toLowerCase();
        const allowedRoles = ['admin', 'customer', 'employee', 'installer', 'referrer'];
        const roleValue = allowedRoles.includes(role) ? role : 'referrer';
        const users = readUsers();
        if (users.some(user => user.email.toLowerCase() === normalisedEmail)) {
          sendJson(res, 409, { error: 'An account with this email already exists.' });
          return;
        }
        const user = {
          id: `usr-${crypto.randomUUID()}`,
          name: String(name).trim(),
          email: normalisedEmail,
          phone: String(phone || '').trim(),
          city: String(city || '').trim(),
          role: roleValue,
          password: createPasswordRecord(String(password)),
          createdAt: new Date().toISOString()
        };
        users.push(user);
        writeUsers(users);
        const token = createToken(user.id);
        sendJson(res, 201, { token, user: sanitizeUser(user) });
      })
      .catch(() => sendJson(res, 400, { error: 'Invalid JSON payload.' }));
    return;
  }

  if (req.method === 'POST' && url.pathname === '/api/login') {
    collectRequestBody(req)
      .then(body => {
        const { email, password } = body;
        if (!email || !password) {
          sendJson(res, 400, { error: 'Email and password are required.' });
          return;
        }
        const users = readUsers();
        const user = users.find(u => u.email.toLowerCase() === String(email).trim().toLowerCase());
        if (!user || !verifyPassword(String(password), user.password)) {
          sendJson(res, 401, { error: 'Invalid credentials. Check your email and password.' });
          return;
        }
        const token = createToken(user.id);
        sendJson(res, 200, { token, user: sanitizeUser(user) });
      })
      .catch(() => sendJson(res, 400, { error: 'Invalid JSON payload.' }));
    return;
  }

  if (req.method === 'GET' && url.pathname === '/api/me') {
    const user = getUserFromToken(req);
    if (!user) {
      sendJson(res, 401, { error: 'Unauthorised' });
      return;
    }
    sendJson(res, 200, { user: sanitizeUser(user) });
    return;
  }

  if (req.method === 'GET' && url.pathname.startsWith('/api/dashboard/')) {
    const user = getUserFromToken(req);
    if (!user) {
      sendJson(res, 401, { error: 'Unauthorised' });
      return;
    }
    const requestedRole = url.pathname.replace('/api/dashboard/', '');
    if (user.role !== requestedRole) {
      sendJson(res, 403, { error: 'You are not allowed to view this dashboard.' });
      return;
    }
    const data = buildDashboardData(user);
    sendJson(res, 200, data);
    return;
  }

  sendNotFound(res);
}

function getContentType(filePath) {
  const ext = path.extname(filePath).toLowerCase();
  const map = {
    '.html': 'text/html; charset=utf-8',
    '.css': 'text/css; charset=utf-8',
    '.js': 'application/javascript; charset=utf-8',
    '.json': 'application/json; charset=utf-8',
    '.png': 'image/png',
    '.jpg': 'image/jpeg',
    '.jpeg': 'image/jpeg',
    '.svg': 'image/svg+xml',
    '.ico': 'image/x-icon'
  };
  return map[ext] || 'application/octet-stream';
}

function serveStaticFile(res, filePath) {
  fs.readFile(filePath, (error, data) => {
    if (error) {
      if (error.code === 'ENOENT') {
        sendNotFound(res);
      } else {
        res.writeHead(500, { 'Content-Type': 'text/plain' });
        res.end('Server error');
      }
      return;
    }
    res.writeHead(200, { 'Content-Type': getContentType(filePath) });
    res.end(data);
  });
}

const server = http.createServer((req, res) => {
  const url = new URL(req.url, `http://${req.headers.host}`);

  if (url.pathname.startsWith('/api/')) {
    handleApiRequest(req, res, url);
    return;
  }

  let filePath = path.join(STATIC_ROOT, url.pathname);
  if (url.pathname === '/' || url.pathname === '') {
    filePath = path.join(STATIC_ROOT, 'index.html');
  }
  if (!path.extname(filePath)) {
    filePath += '.html';
  }
  serveStaticFile(res, filePath);
});

server.listen(PORT, HOST, () => {
  ensureDataDirectory();
  readUsers();
  console.log(`Portal API and static server running at http://${HOST}:${PORT}`);
});

