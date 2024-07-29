import { defaultEmailBody, defaultEmailSubject } from '../utils';

const getNotificationResponse = ({
  isBamModuleInstalled
}: {
  isBamModuleInstalled?: boolean;
}): object => ({
  contactgroups: [
    {
      id: 1,
      name: 'contact-group1'
    },
    {
      id: 2,
      name: 'contact-group2'
    }
  ],
  id: 1,
  is_activated: false,
  messages: [
    {
      channel: 'Email',
      formatted_message: '',
      message:
        '{"root":{"children":[{"children":[{"detail":0,"format":0,"mode":"normal","style":"","text":"Hello ","type":"text","version":1},{"detail":0,"format":1,"mode":"normal","style":"","text":"there","type":"text","version":1},{"detail":0,"format":0,"mode":"normal","style":"","text":",","type":"text","version":1}],"direction":"ltr","format":"","indent":0,"type":"paragraph","version":1},{"children":[{"detail":0,"format":0,"mode":"normal","style":"","text":"How are you doing so ","type":"text","version":1},{"detail":0,"format":12,"mode":"normal","style":"","text":"far","type":"text","version":1},{"detail":0,"format":0,"mode":"normal","style":"","text":"?","type":"text","version":1}],"direction":"ltr","format":"","indent":0,"type":"heading","version":1,"tag":"h5"}],"direction":"ltr","format":"","indent":0,"type":"root","version":1}}',
      subject: 'Notification'
    }
  ],
  name: 'Notifications 1',
  resources: [
    {
      events: 3,
      extra: {
        event_services: 3
      },
      ids: [
        {
          id: 60,
          name: 'Firewall'
        },
        {
          id: 53,
          name: 'Linux-Servers'
        },
        {
          id: 55,
          name: 'Networks'
        },
        {
          id: 56,
          name: 'Printers'
        }
      ],
      type: 'hostgroup'
    },
    {
      events: 14,
      ids: [
        {
          id: 1,
          name: 'service1'
        },
        {
          id: 2,
          name: 'service2'
        },
        {
          id: 3,
          name: 'service3'
        }
      ],
      type: 'servicegroup'
    },
    ...(isBamModuleInstalled
      ? [
          {
            events: 14,
            ids: [
              {
                id: 1,
                name: 'bv1'
              },
              {
                id: 2,
                name: 'bv2'
              }
            ],
            type: 'businessview'
          }
        ]
      : [])
  ],
  timeperiod: {
    id: 1,
    name: '24h/24 - 7/7 days'
  },
  users: [
    {
      id: 4,
      name: 'centreon-gorgone'
    },
    {
      id: 17,
      name: 'Guest'
    }
  ]
});

const hostGroupsResponse = {
  meta: {
    limit: 10,
    page: 1,
    search: {},
    sort_by: {},
    total: 1
  },
  result: [
    {
      id: 60,
      name: 'Firewall'
    },
    {
      id: 53,
      name: 'Linux-Servers'
    },
    {
      id: 55,
      name: 'Networks'
    },
    {
      id: 56,
      name: 'Printers'
    }
  ]
};

const serviceGroupsResponse = {
  meta: {
    limit: 10,
    page: 1,
    search: {},
    sort_by: {},
    total: 1
  },
  result: [
    {
      id: 1,
      name: 'MySQL-Servers'
    }
  ]
};

const usersResponse = {
  meta: {
    limit: 10,
    page: 1,
    search: {},
    sort_by: {},
    total: 1
  },
  result: [
    {
      id: 4,
      name: 'centreon-gorgone'
    },
    {
      id: 17,
      name: 'Guest'
    }
  ]
};

const platformVersions = {
  isCloudPlatform: false,
  modules: {
    'centreon-bam-server': {
      fix: '0',
      major: '23',
      minor: '10',
      version: '23.10.0'
    }
  },
  web: {
    fix: '0',
    major: '23',
    minor: '10',
    version: '23.10.0'
  },
  widgets: {}
};

const formData = {
  contactgroups: [],
  is_activated: true,
  messages: [
    {
      channel: 'Email',
      formatted_message:
        '<p class="css-1qf631s-paragraph" dir="ltr"><b><strong class="css-1jxftah-bold" style="white-space: pre-wrap;">Centreon notification</strong></b><br><br><span style="white-space: pre-wrap;">Notification Type: </span><b><strong class="css-1jxftah-bold" style="white-space: pre-wrap;">{{NOTIFICATIONTYPE}}</strong></b><br><br><span style="white-space: pre-wrap;">Resource: {{NAME}}</span><br><br><span style="white-space: pre-wrap;">State: </span><b><strong class="css-1jxftah-bold" style="white-space: pre-wrap;">{{STATE}}</strong></b><br><br><span style="white-space: pre-wrap;">Date/Time: {{SHORTDATETIME}}</span><br><br><span style="white-space: pre-wrap;">Additional Info: {{OUTPUT}}</span></p>',
      message: defaultEmailBody,
      subject: defaultEmailSubject
    }
  ],
  name: 'notification#1',
  resources: [
    {
      events: 0,
      extra: {
        event_services: 0
      },
      ids: [60],
      type: 'hostgroup'
    },
    {
      events: 0,
      ids: [1],
      type: 'servicegroup'
    }
  ],
  timeperiod_id: '1',
  users: [17]
};

const timePeriodsResponse = {
  meta: {
    limit: 10,
    page: 1,
    search: {},
    sort_by: {},
    total: 1
  },
  result: [
    {
      alias: '24 x 7',
      days: [
        {
          day: 1,
          time_range: '09:00-17:00'
        },
        {
          day: 2,
          time_range: '09:00-17:00'
        },
        {
          day: 3,
          time_range: '09:00-17:00'
        },
        {
          day: 4,
          time_range: '09:00-17:00'
        },
        {
          day: 5,
          time_range: '09:00-17:00'
        }
      ],
      exceptions: [],
      id: '1',
      name: '24X7',
      templates: []
    },
    {
      alias: 'Never',
      days: [],
      exceptions: [],
      id: 2,
      name: 'none',
      templates: []
    }
  ]
};

const emailBodyText = [
  'Centreon notification',
  'Notification Type: {{NOTIFICATIONTYPE}}',
  'Resource: {{NAME}}',
  'State: {{STATE}}',
  'Date/Time: {{SHORTDATETIME}}',
  'Additional Info: {{OUTPUT}}'
];

export {
  formData,
  usersResponse,
  getNotificationResponse,
  hostGroupsResponse,
  serviceGroupsResponse,
  platformVersions,
  emailBodyText,
  timePeriodsResponse
};
