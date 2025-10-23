(function () {
  const dashboards = {
    admin: {
      headline: 'Company operations overview',
      metrics: [
        { label: 'Active dashboards', value: '5', helper: 'All roles reachable' },
        { label: 'Pending tickets', value: '9', helper: '3 need escalation' },
        { label: 'Monthly revenue', value: '₹42.5L', helper: 'Updated hourly' }
      ],
      timeline: [
        { label: 'Board review', date: '15 Oct 2024', status: 'Scheduled' },
        { label: 'Compliance audit', date: '22 Oct 2024', status: 'Preparing' },
        { label: 'KPI newsletter', date: '25 Oct 2024', status: 'Drafting' }
      ],
      tasks: [
        { label: 'Approve installer onboarding requests', status: 'Pending' },
        { label: 'Publish Q3 performance summary', status: 'In progress' },
        { label: 'Review financing partner contract', status: 'Due Friday' }
      ]
    },
    customer: {
      headline: 'Your solar project status',
      metrics: [
        { label: 'Installation progress', value: '80%', helper: 'Net metering pending' },
        { label: 'Lifetime energy saved', value: '5120 kWh', helper: 'Updated daily' },
        { label: 'Projected savings', value: '₹14,200', helper: 'This quarter' }
      ],
      timeline: [
        { label: 'Site survey', date: '30 Sep 2024', status: 'Completed' },
        { label: 'Structural approval', date: '10 Oct 2024', status: 'Completed' },
        { label: 'Electrical inspection', date: '18 Oct 2024', status: 'Scheduled' }
      ],
      tasks: [
        { label: 'Upload latest electricity bill', status: 'Pending' },
        { label: 'Confirm installer access window', status: 'Scheduled' },
        { label: 'Review financing documents', status: 'In progress' }
      ]
    },
    employee: {
      headline: 'Customer success focus',
      metrics: [
        { label: 'Assigned tickets', value: '24', helper: '5 due today' },
        { label: 'Customer CSAT', value: '4.7 / 5', helper: 'Rolling 30 days' },
        { label: 'Pending escalations', value: '2', helper: 'Awaiting approval' }
      ],
      timeline: [
        { label: 'Installer sync', date: '11 Oct 2024, 10:00', status: 'Today' },
        { label: 'Success review', date: '13 Oct 2024, 15:30', status: 'Scheduled' },
        { label: 'Knowledge base update', date: '19 Oct 2024', status: 'Drafting' }
      ],
      tasks: [
        { label: 'Call customer DE-2041 about inspection', status: 'Pending' },
        { label: 'Update CRM for project JSR-118', status: 'In progress' },
        { label: 'Submit weekly activity summary', status: 'Due Friday' }
      ]
    },
    installer: {
      headline: 'Field execution overview',
      metrics: [
        { label: 'Jobs this week', value: '6', helper: '2 require structural clearance' },
        { label: 'Average completion time', value: '6.5 hrs', helper: 'Across active jobs' },
        { label: 'Safety checklist', value: '100%', helper: 'All audits passed' }
      ],
      timeline: [
        { label: 'Ranchi - Verma residence', date: '11 Oct 2024, 09:00', status: 'Team A' },
        { label: 'Jamshedpur - Patel industries', date: '12 Oct 2024, 14:00', status: 'Team C' },
        { label: 'Bokaro - Singh clinic', date: '14 Oct 2024, 08:30', status: 'Team B' }
      ],
      tasks: [
        { label: 'Upload as-built photos for Ranchi site', status: 'Pending' },
        { label: 'Collect inverter serial numbers', status: 'In progress' },
        { label: 'Confirm material delivery for Dhanbad project', status: 'Scheduled' }
      ]
    },
    referrer: {
      headline: 'Referral programme snapshot',
      metrics: [
        { label: 'Active leads', value: '14', helper: '4 new this week' },
        { label: 'Conversion rate', value: '28%', helper: 'Trailing 90 days' },
        { label: 'Rewards earned', value: '₹36,500', helper: 'Next payout on 20 Oct' }
      ],
      timeline: [
        { label: 'Lead RF-882 follow-up', date: '11 Oct 2024', status: 'Call scheduled' },
        { label: 'Payout reconciliation', date: '15 Oct 2024', status: 'Processing' },
        { label: 'Referral webinar', date: '20 Oct 2024, 17:00', status: 'Open for registration' }
      ],
      tasks: [
        { label: 'Share site photos for lead RF-876', status: 'Pending' },
        { label: 'Confirm bank details for rewards', status: 'In progress' },
        { label: 'Invite 3 new prospects', status: 'Stretch goal' }
      ]
    }
  };

  const employeePortal = {
    profile: {
      deskLocation: 'Jamshedpur HQ · Pod B',
      workingHours: '10:00 – 18:30 IST',
      emergencyContact: 'Ritika Sharma · +91 98450 22110',
      preferredChannel: 'Phone call',
      reportingManager: 'Sneha Kapoor',
      serviceRegion: 'Jamshedpur & Bokaro cluster'
    },
    pipeline: [
      { stage: 'New intake', count: 6, note: 'Welcome calls pending — target response within 2 hours.' },
      { stage: 'In progress', count: 12, note: 'Follow-ups booked for inspection confirmations.' },
      { stage: 'Awaiting documents', count: 4, note: 'Customers to upload net-metering paperwork.', sla: 'Send reminders before 17:00 IST.' },
      { stage: 'Escalated', count: 2, note: 'Finance holds on invoices DE-2041 and PKL-552.' }
    ],
    sentimentWatch: [
      {
        customer: 'DE-2041 · Ranchi',
        sentiment: 'At risk',
        nextStep: 'Inspection reschedule pending — call before 14:00 IST.',
        owner: 'You',
        due: '11 Oct 2024, 14:00',
        trend: 'Needs attention'
      },
      {
        customer: 'JSR-118 · Jamshedpur',
        sentiment: 'Recovering',
        nextStep: 'Share inverter replacement plan on WhatsApp.',
        owner: 'Amit (Field lead)',
        due: '12 Oct 2024, 10:30',
        trend: 'Improving'
      },
      {
        customer: 'PKL-552 · Bokaro',
        sentiment: 'Monitor',
        nextStep: 'Waiting on subsidy paperwork confirmation.',
        owner: 'You',
        due: '13 Oct 2024, 09:00',
        trend: 'Follow closely'
      }
    ],
    approvals: [
      {
        id: 'APP-1092',
        title: 'Payroll bank account update',
        status: 'Pending admin review',
        submitted: '09 Oct 2024, 11:45',
        owner: 'Finance & Admin',
        details: 'New salary account at SBI Jamshedpur (DLF branch) shared for verification.',
        effectiveDate: '12 Oct 2024',
        lastUpdate: 'Finance team verifying cancelled cheque'
      },
      {
        id: 'APP-1086',
        title: 'CRM access scope change',
        status: 'Approved',
        submitted: '04 Oct 2024, 16:20',
        owner: 'Admin desk',
        details: 'Escalation board access requested for Bokaro cluster support.',
        effectiveDate: '06 Oct 2024',
        lastUpdate: 'Approved by Vishesh on 06 Oct 2024'
      }
    ],
    approvalHistory: [
      {
        id: 'APP-1079',
        title: 'Hardware allowance claim',
        status: 'Completed',
        resolved: '29 Sep 2024',
        outcome: 'Reimbursement of ₹3,500 processed to payroll.'
      },
      {
        id: 'APP-1062',
        title: 'Shift swap request',
        status: 'Declined',
        resolved: '22 Sep 2024',
        outcome: 'Declined — Sunday evening coverage required for onboarding window.'
      }
    ]
  };

  window.DAKSHAYANI_PORTAL_DEMO = Object.freeze({ users: [], dashboards, employeePortal });
})();
