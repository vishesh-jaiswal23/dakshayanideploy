const http = require('http');
const path = require('path');
const fs = require('fs');
const { URL } = require('url');
const https = require('https');
const crypto = require('crypto');

const PORT = process.env.PORT || 4000;
const HOST = process.env.HOST || '0.0.0.0';
const DATA_DIR = path.join(__dirname, 'data');
const USERS_FILE = path.join(DATA_DIR, 'users.json');
const SITE_SETTINGS_FILE = path.join(DATA_DIR, 'site-settings.json');
const STATIC_ROOT = path.join(__dirname, '..');

const WHATSAPP_PHONE_NUMBER_ID = process.env.WHATSAPP_PHONE_NUMBER_ID || '';
const WHATSAPP_ACCESS_TOKEN = process.env.WHATSAPP_ACCESS_TOKEN || '';
const WHATSAPP_RECIPIENT_NUMBER =
  process.env.WHATSAPP_RECIPIENT_NUMBER || process.env.WHATSAPP_RECIPIENT || '';

const sessions = new Map();
const ALLOWED_FESTIVAL_THEMES = new Set(['default', 'diwali', 'holi', 'christmas']);
const ROLE_OPTIONS = ['admin', 'customer', 'employee', 'installer', 'referrer'];
const USER_STATUSES = ['active', 'suspended'];

function ensureDataDirectory() {
  if (!fs.existsSync(DATA_DIR)) {
    fs.mkdirSync(DATA_DIR, { recursive: true });
  }
}

function defaultSiteSettings() {
  return {
    festivalTheme: 'default',
    hero: {
      title: 'Cut Your Electricity Bills. Power Your Future.',
      subtitle: 'Join 500+ Jharkhand families saving lakhs with dependable rooftop and hybrid solar solutions designed around you.',
      primaryImage: 'images/hero/hero.png',
      primaryAlt: 'Dakshayani engineers installing a rooftop solar plant',
      primaryCaption: 'Live commissioning | Ranchi',
      bubbleHeading: '24/7 monitoring',
      bubbleBody: 'Hybrid + storage ready',
      gallery: [
        { image: 'images/residential pics real/IMG-20230407-WA0011.jpg', caption: 'Residential handover' },
        { image: 'images/finance.jpg', caption: 'Finance desk' }
      ]
    },
    installs: [
      {
        id: 'install-001',
        title: '8 kW Duplex Rooftop',
        location: 'Ranchi, Jharkhand',
        capacity: '8 kW',
        completedOn: 'October 2024',
        image: 'images/residential pics real/IMG-20241028-WA0002.jpg',
        summary: 'Sun-tracking friendly design for an east-west duplex with surge protection and earthing upgrades.'
      },
      {
        id: 'install-002',
        title: '35 kW Manufacturing Retrofit',
        location: 'Adityapur, Jharkhand',
        capacity: '35 kW',
        completedOn: 'August 2024',
        image: 'images/residential pics real/WhatsApp Image 2025-02-10 at 17.44.29_6f1624c9.jpg',
        summary: 'Retrofit on light-gauge roofing with optimisers to balance shading across production bays.'
      },
      {
        id: 'install-003',
        title: 'Solar Irrigation Pump Cluster',
        location: 'Khunti, Jharkhand',
        capacity: '15 HP',
        completedOn: 'July 2024',
        image: 'images/pump.jpg',
        summary: 'High-efficiency AC pump with remote diagnostics energising micro-irrigation for farmers.'
      }
    ]
  };
}

function sanitizeSiteSettings(settings) {
  if (!settings || typeof settings !== 'object') {
    return defaultSiteSettings();
  }

  const defaults = defaultSiteSettings();
  const hero = settings.hero && typeof settings.hero === 'object' ? settings.hero : {};
  const gallery = Array.isArray(hero.gallery) ? hero.gallery : [];
  const installs = Array.isArray(settings.installs) ? settings.installs : [];

  return {
    festivalTheme: typeof settings.festivalTheme === 'string' ? settings.festivalTheme : defaults.festivalTheme,
    hero: {
      title: typeof hero.title === 'string' ? hero.title : defaults.hero.title,
      subtitle: typeof hero.subtitle === 'string' ? hero.subtitle : defaults.hero.subtitle,
      primaryImage: typeof hero.primaryImage === 'string' ? hero.primaryImage : defaults.hero.primaryImage,
      primaryAlt: typeof hero.primaryAlt === 'string' ? hero.primaryAlt : defaults.hero.primaryAlt,
      primaryCaption: typeof hero.primaryCaption === 'string' ? hero.primaryCaption : defaults.hero.primaryCaption,
      bubbleHeading: typeof hero.bubbleHeading === 'string' ? hero.bubbleHeading : defaults.hero.bubbleHeading,
      bubbleBody: typeof hero.bubbleBody === 'string' ? hero.bubbleBody : defaults.hero.bubbleBody,
      gallery: gallery
        .slice(0, 6)
        .map((item, index) => {
          const fallback = defaults.hero.gallery[index] || defaults.hero.gallery[0];
          return {
            image: typeof item?.image === 'string' ? item.image : fallback.image,
            caption: typeof item?.caption === 'string' ? item.caption : fallback.caption
          };
        })
    },
    installs: installs
      .slice(0, 8)
      .map((install, index) => {
        const fallback = defaults.installs[index % defaults.installs.length];
        return {
          id: typeof install?.id === 'string' ? install.id : `install-${index + 1}`,
          title: typeof install?.title === 'string' ? install.title : fallback.title,
          location: typeof install?.location === 'string' ? install.location : fallback.location,
          capacity: typeof install?.capacity === 'string' ? install.capacity : fallback.capacity,
          completedOn: typeof install?.completedOn === 'string' ? install.completedOn : fallback.completedOn,
          image: typeof install?.image === 'string' ? install.image : fallback.image,
          summary: typeof install?.summary === 'string' ? install.summary : fallback.summary
        };
      })
  };
}

function readSiteSettings() {
  ensureDataDirectory();
  if (!fs.existsSync(SITE_SETTINGS_FILE)) {
    const defaults = defaultSiteSettings();
    fs.writeFileSync(SITE_SETTINGS_FILE, JSON.stringify(defaults, null, 2));
    return defaults;
  }

  const raw = fs.readFileSync(SITE_SETTINGS_FILE, 'utf8');
  try {
    const parsed = JSON.parse(raw || '{}');
    const sanitized = sanitizeSiteSettings(parsed);
    fs.writeFileSync(SITE_SETTINGS_FILE, JSON.stringify(sanitized, null, 2));
    return sanitized;
  } catch (error) {
    console.error('Failed to parse site settings, resetting to defaults.', error);
    const defaults = defaultSiteSettings();
    fs.writeFileSync(SITE_SETTINGS_FILE, JSON.stringify(defaults, null, 2));
    return defaults;
  }
}

function writeSiteSettings(settings) {
  const sanitized = sanitizeSiteSettings(settings);
  fs.writeFileSync(SITE_SETTINGS_FILE, JSON.stringify(sanitized, null, 2));
  return sanitized;
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

  const ensureShape = (user) => {
    if (!user || typeof user !== 'object') {
      return;
    }

    if (!user.id) {
      user.id = `usr-${crypto.randomUUID()}`;
    }

    if (!ROLE_OPTIONS.includes(user.role)) {
      user.role = 'referrer';
    }

    user.status = USER_STATUSES.includes(user.status) ? user.status : 'active';

    const createdAt = user.createdAt || new Date().toISOString();
    user.createdAt = createdAt;
    user.updatedAt = user.updatedAt || createdAt;
    user.passwordChangedAt = user.passwordChangedAt || user.updatedAt;

    if (!user.password || typeof user.password !== 'object' || !user.password.salt) {
      user.password = createPasswordRecord('ChangeMe@123');
    }
  };

  users.forEach(ensureShape);

  const ensureUser = (email, creator) => {
    if (!users.some(user => user.email.toLowerCase() === email.toLowerCase())) {
      const user = creator();
      ensureShape(user);
      users.push(user);
    }
  };

  ensureUser('admin@dakshayani.in', () => ({
    id: 'usr-admin-1',
    name: 'Dakshayani Admin',
    email: 'admin@dakshayani.in',
    phone: '+91 70000 00000',
    city: 'Ranchi',
    role: 'admin',
    status: 'active',
    password: createPasswordRecord('Admin@123'),
    createdAt: new Date().toISOString(),
    updatedAt: new Date().toISOString()
  }));

  ensureUser('customer@dakshayani.in', () => ({
    id: 'usr-customer-1',
    name: 'Asha Verma',
    email: 'customer@dakshayani.in',
    phone: '+91 90000 00000',
    city: 'Jamshedpur',
    role: 'customer',
    status: 'active',
    password: createPasswordRecord('Customer@123'),
    createdAt: new Date().toISOString(),
    updatedAt: new Date().toISOString()
  }));

  ensureUser('employee@dakshayani.in', () => ({
    id: 'usr-employee-1',
    name: 'Rohit Kumar',
    email: 'employee@dakshayani.in',
    phone: '+91 88000 00000',
    city: 'Bokaro',
    role: 'employee',
    status: 'active',
    password: createPasswordRecord('Employee@123'),
    createdAt: new Date().toISOString(),
    updatedAt: new Date().toISOString()
  }));

  ensureUser('installer@dakshayani.in', () => ({
    id: 'usr-installer-1',
    name: 'Sunita Singh',
    email: 'installer@dakshayani.in',
    phone: '+91 86000 00000',
    city: 'Dhanbad',
    role: 'installer',
    status: 'active',
    password: createPasswordRecord('Installer@123'),
    createdAt: new Date().toISOString(),
    updatedAt: new Date().toISOString()
  }));

  ensureUser('referrer@dakshayani.in', () => ({
    id: 'usr-referrer-1',
    name: 'Sanjay Patel',
    email: 'referrer@dakshayani.in',
    phone: '+91 94000 00000',
    city: 'Hazaribagh',
    role: 'referrer',
    status: 'active',
    password: createPasswordRecord('Referrer@123'),
    createdAt: new Date().toISOString(),
    updatedAt: new Date().toISOString()
  }));

  return users;
}

function normaliseEmail(value) {
  return String(value || '').trim().toLowerCase();
}

function isValidPassword(password) {
  return typeof password === 'string' && password.length >= 8;
}

function computeUserStats(users) {
  const stats = { total: 0, roles: {} };
  if (!Array.isArray(users)) {
    return stats;
  }
  stats.total = users.length;
  users.forEach((user) => {
    const role = ROLE_OPTIONS.includes(user.role) ? user.role : 'other';
    stats.roles[role] = (stats.roles[role] || 0) + 1;
  });
  return stats;
}

function prepareUsersForResponse(users) {
  if (!Array.isArray(users)) {
    return [];
  }
  return [...users]
    .map((user) => sanitizeUser(user))
    .sort((a, b) => {
      const left = new Date(a.createdAt || 0).getTime();
      const right = new Date(b.createdAt || 0).getTime();
      return right - left;
    });
}

function countActiveAdmins(users) {
  if (!Array.isArray(users)) {
    return 0;
  }
  return users.filter((user) => user.role === 'admin' && user.status !== 'suspended').length;
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

function isWhatsAppConfigured() {
  return (
    Boolean(WHATSAPP_PHONE_NUMBER_ID) &&
    Boolean(WHATSAPP_ACCESS_TOKEN) &&
    Boolean(WHATSAPP_RECIPIENT_NUMBER)
  );
}

function buildWhatsAppLeadMessage(lead) {
  const timestamp = new Date().toLocaleString('en-IN', { timeZone: 'Asia/Kolkata' });
  const lines = [
    '*New Solar Consultation Lead*',
    `Name: ${lead.name}`,
    `Phone: ${lead.phone}`,
    `City: ${lead.city}`,
    `Project Type: ${lead.projectType}`,
  ];

  if (lead.leadSource) {
    lines.push(`Source: ${lead.leadSource}`);
  }

  lines.push(`Received: ${timestamp}`);
  return lines.join('\n');
}

function sendWhatsAppLeadNotification(lead) {
  return new Promise((resolve, reject) => {
    if (!isWhatsAppConfigured()) {
      reject(new Error('WhatsApp integration is not configured.'));
      return;
    }

    const recipient = String(WHATSAPP_RECIPIENT_NUMBER).replace(/[^+\d]/g, '');
    const message = buildWhatsAppLeadMessage(lead);
    const payload = JSON.stringify({
      messaging_product: 'whatsapp',
      to: recipient,
      type: 'text',
      text: {
        preview_url: false,
        body: message,
      },
    });

    const request = https.request(
      `https://graph.facebook.com/v20.0/${WHATSAPP_PHONE_NUMBER_ID}/messages`,
      {
        method: 'POST',
        headers: {
          Authorization: `Bearer ${WHATSAPP_ACCESS_TOKEN}`,
          'Content-Type': 'application/json',
          'Content-Length': Buffer.byteLength(payload),
        },
      },
      (response) => {
        const chunks = [];
        response.on('data', (chunk) => chunks.push(chunk));
        response.on('end', () => {
          const body = Buffer.concat(chunks).toString('utf8');
          if (response.statusCode >= 200 && response.statusCode < 300) {
            resolve(body);
          } else {
            const error = new Error(
              `WhatsApp API responded with status ${response.statusCode}: ${body}`
            );
            error.statusCode = response.statusCode;
            reject(error);
          }
        });
      }
    );

    request.on('error', reject);
    request.write(payload);
    request.end();
  });
}

function createToken(userId) {
  const token = crypto.randomBytes(32).toString('hex');
  sessions.set(token, { userId, createdAt: Date.now() });
  return token;
}

function revokeSessionsForUser(userId) {
  if (!userId) return;
  for (const [token, session] of sessions.entries()) {
    if (session.userId === userId) {
      sessions.delete(token);
    }
  }
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
        const normalisedEmail = normaliseEmail(email);
        const roleValue = ROLE_OPTIONS.includes(role) ? role : 'referrer';
        const users = readUsers();
        if (users.some(user => user.email.toLowerCase() === normalisedEmail)) {
          sendJson(res, 409, { error: 'An account with this email already exists.' });
          return;
        }
        const timestamp = new Date().toISOString();
        const user = {
          id: `usr-${crypto.randomUUID()}`,
          name: String(name).trim(),
          email: normalisedEmail,
          phone: String(phone || '').trim(),
          city: String(city || '').trim(),
          role: roleValue,
          password: createPasswordRecord(String(password)),
          status: 'active',
          createdAt: timestamp,
          updatedAt: timestamp,
          passwordChangedAt: timestamp
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
        const user = users.find(u => u.email.toLowerCase() === normaliseEmail(email));
        if (!user || !verifyPassword(String(password), user.password)) {
          sendJson(res, 401, { error: 'Invalid credentials. Check your email and password.' });
          return;
        }
        if (user.status && user.status !== 'active') {
          sendJson(res, 403, { error: 'This account is suspended. Contact the administrator.' });
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

  if (req.method === 'GET' && url.pathname === '/api/public/site-settings') {
    const settings = readSiteSettings();
    sendJson(res, 200, { settings: sanitizeSiteSettings(settings) });
    return;
  }

  if (req.method === 'POST' && url.pathname === '/api/leads/whatsapp') {
    collectRequestBody(req)
      .then((body) => {
        const name = String(body?.name || '').trim();
        const phone = String(body?.phone || '').trim();
        const city = String(body?.city || '').trim();
        const projectType = String(body?.projectType || '').trim();
        const leadSource = String(body?.leadSource || 'Website Homepage').trim();

        if (!name || !phone || !city || !projectType) {
          sendJson(res, 400, { error: 'Name, phone, city, and project type are required.' });
          return;
        }

        if (!isWhatsAppConfigured()) {
          sendJson(res, 503, {
            error: 'WhatsApp integration is not configured on the server. Please contact support.',
          });
          return;
        }

        const lead = {
          name: name.replace(/\s+/g, ' ').trim(),
          phone,
          city: city.replace(/\s+/g, ' ').trim(),
          projectType,
          leadSource,
        };

        sendWhatsAppLeadNotification(lead)
          .then(() => {
            sendJson(res, 200, { message: 'Lead forwarded to WhatsApp successfully.' });
          })
          .catch((error) => {
            console.error('Failed to forward lead to WhatsApp', error);
            const statusCode = error?.statusCode || 502;
            const message =
              statusCode === 401 || statusCode === 403
                ? 'WhatsApp authentication failed. Verify the server access token.'
                : 'Unable to forward the lead to WhatsApp at this time. Please try again later.';
            sendJson(res, 502, { error: message });
          });
      })
      .catch(() => sendJson(res, 400, { error: 'Invalid JSON payload.' }));
    return;
  }

  if (url.pathname === '/api/admin/users') {
    const actor = getUserFromToken(req);
    if (!actor) {
      sendJson(res, 401, { error: 'Unauthorised' });
      return;
    }
    if (actor.role !== 'admin') {
      sendJson(res, 403, { error: 'You are not allowed to manage users.' });
      return;
    }

    if (req.method === 'GET') {
      const users = readUsers();
      sendJson(res, 200, {
        users: prepareUsersForResponse(users),
        stats: computeUserStats(users),
        refreshedAt: new Date().toISOString()
      });
      return;
    }

    if (req.method === 'POST') {
      collectRequestBody(req)
        .then((body) => {
          const displayName = String(body?.name || '').trim();
          const email = normaliseEmail(body?.email);
          const password = body?.password;
          const role = ROLE_OPTIONS.includes(body?.role) ? body.role : 'referrer';
          const status = USER_STATUSES.includes(body?.status) ? body.status : 'active';
          const phone = String(body?.phone || '').trim();
          const city = String(body?.city || '').trim();

          if (!displayName || !email) {
            sendJson(res, 400, { error: 'Name and email are required.' });
            return;
          }

          if (!isValidPassword(password)) {
            sendJson(res, 400, { error: 'Password must be at least 8 characters long.' });
            return;
          }

          const users = readUsers();
          if (users.some((user) => user.email.toLowerCase() === email)) {
            sendJson(res, 409, { error: 'An account with this email already exists.' });
            return;
          }

          const timestamp = new Date().toISOString();
          const user = {
            id: `usr-${crypto.randomUUID()}`,
            name: displayName,
            email,
            phone,
            city,
            role,
            status,
            password: createPasswordRecord(String(password)),
            createdAt: timestamp,
            updatedAt: timestamp,
            passwordChangedAt: timestamp,
            createdBy: actor.id
          };

          users.push(user);
          writeUsers(users);
          sendJson(res, 201, {
            user: sanitizeUser(user),
            stats: computeUserStats(users)
          });
        })
        .catch(() => sendJson(res, 400, { error: 'Invalid JSON payload.' }));
      return;
    }

    sendJson(res, 405, { error: 'Method not allowed.' });
    return;
  }

  if (url.pathname.startsWith('/api/admin/users/')) {
    const segments = url.pathname.split('/').filter(Boolean);
    const userId = segments[3] ? decodeURIComponent(segments[3]) : null;
    const action = segments[4] ? decodeURIComponent(segments[4]) : null;
    if (!userId) {
      sendNotFound(res);
      return;
    }

    const actor = getUserFromToken(req);
    if (!actor) {
      sendJson(res, 401, { error: 'Unauthorised' });
      return;
    }
    if (actor.role !== 'admin') {
      sendJson(res, 403, { error: 'You are not allowed to manage users.' });
      return;
    }

    const users = readUsers();
    const index = users.findIndex((user) => user.id === userId);
    if (index === -1) {
      sendJson(res, 404, { error: 'User not found.' });
      return;
    }

    const target = users[index];

    if (action === 'reset-password') {
      if (req.method !== 'POST') {
        sendJson(res, 405, { error: 'Method not allowed.' });
        return;
      }

      collectRequestBody(req)
        .then((body) => {
          const password = body?.password;
          if (!isValidPassword(password)) {
            sendJson(res, 400, { error: 'Password must be at least 8 characters long.' });
            return;
          }

          const timestamp = new Date().toISOString();
          target.password = createPasswordRecord(String(password));
          target.passwordChangedAt = timestamp;
          target.updatedAt = timestamp;
          target.updatedBy = actor.id;
          writeUsers(users);
          revokeSessionsForUser(target.id);
          sendJson(res, 200, { user: sanitizeUser(target) });
        })
        .catch(() => sendJson(res, 400, { error: 'Invalid JSON payload.' }));
      return;
    }

    if (action) {
      sendNotFound(res);
      return;
    }

    if (req.method === 'PUT') {
      collectRequestBody(req)
        .then((body) => {
          const nextName = String((body?.name ?? target.name) || '').trim();
          const nextPhone = String((body?.phone ?? target.phone) || '').trim();
          const nextCity = String((body?.city ?? target.city) || '').trim();
          const nextRole = ROLE_OPTIONS.includes(body?.role) ? body.role : target.role;
          const nextStatus = USER_STATUSES.includes(body?.status) ? body.status : target.status;

          const activeAdmins = countActiveAdmins(users);
          const targetIsActiveAdmin = target.role === 'admin' && target.status !== 'suspended';
          const demotingAdmin = targetIsActiveAdmin && (nextRole !== 'admin' || nextStatus !== 'active');

          if (demotingAdmin && activeAdmins <= 1) {
            sendJson(res, 400, { error: 'At least one active admin account must remain.' });
            return;
          }

          if (target.id === actor.id && nextStatus !== 'active') {
            sendJson(res, 400, { error: 'You cannot suspend your own admin account.' });
            return;
          }

          target.name = nextName || target.name;
          target.phone = nextPhone;
          target.city = nextCity;
          target.role = nextRole;
          target.status = nextStatus;
          target.updatedAt = new Date().toISOString();
          target.updatedBy = actor.id;

          writeUsers(users);
          revokeSessionsForUser(target.id);
          sendJson(res, 200, {
            user: sanitizeUser(target),
            stats: computeUserStats(users)
          });
        })
        .catch(() => sendJson(res, 400, { error: 'Invalid JSON payload.' }));
      return;
    }

    if (req.method === 'DELETE') {
      if (target.id === actor.id) {
        sendJson(res, 400, { error: 'You cannot delete your own account.' });
        return;
      }
      if (target.role === 'admin' && countActiveAdmins(users) <= 1) {
        sendJson(res, 400, { error: 'At least one active admin account must remain.' });
        return;
      }

      const removed = users.splice(index, 1)[0];
      writeUsers(users);
      revokeSessionsForUser(removed.id);
      sendJson(res, 200, { stats: computeUserStats(users) });
      return;
    }

    sendJson(res, 405, { error: 'Method not allowed.' });
    return;
  }

  if (url.pathname === '/api/admin/site-settings') {
    const user = getUserFromToken(req);
    if (!user) {
      sendJson(res, 401, { error: 'Unauthorised' });
      return;
    }
    if (user.role !== 'admin') {
      sendJson(res, 403, { error: 'You are not allowed to manage site settings.' });
      return;
    }

    if (req.method === 'GET') {
      const settings = readSiteSettings();
      sendJson(res, 200, { settings: sanitizeSiteSettings(settings) });
      return;
    }

    if (req.method === 'PUT') {
      collectRequestBody(req)
        .then((body) => {
          const current = readSiteSettings();
          const next = { ...current };

          if (body && typeof body === 'object') {
            const candidateTheme = typeof body.festivalTheme === 'string' ? body.festivalTheme : current.festivalTheme;
            next.festivalTheme = ALLOWED_FESTIVAL_THEMES.has(candidateTheme) ? candidateTheme : 'default';

            if (body.hero && typeof body.hero === 'object') {
              next.hero = {
                ...current.hero,
                ...body.hero,
                gallery: Array.isArray(body.hero.gallery) ? body.hero.gallery : current.hero.gallery
              };
            }

            if (Array.isArray(body.installs)) {
              next.installs = body.installs.filter((install) => install && typeof install === 'object');
            }
          }

          const saved = writeSiteSettings(next);
          sendJson(res, 200, { settings: sanitizeSiteSettings(saved) });
        })
        .catch(() => sendJson(res, 400, { error: 'Invalid JSON payload.' }));
      return;
    }
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
  readSiteSettings();
  console.log(`Portal API and static server running at http://${HOST}:${PORT}`);
});

