const listNotificationResponse = {
  id: 1,
  is_activated: false,
  messages: [
    {
      channel: 'Email',
      message:
        '{"root":{"children":[{"children":[{"detail":0,"format":0,"mode":"normal","style":"","text":"Bonjour","type":"text","version":1},{"type":"linebreak","version":1},{"detail":0,"format":0,"mode":"normal","style":"","text":"J\'espére que vous allez bien  ","type":"text","version":1},{"type":"linebreak","version":1},{"detail":0,"format":0,"mode":"normal","style":"","text":"veuillez trouver ci-joint mes réponses à la fiche de candidature","type":"text","version":1},{"type":"linebreak","version":1},{"type":"linebreak","version":1},{"detail":0,"format":0,"mode":"normal","style":"","text":"Cordialement","type":"text","version":1}],"direction":"ltr","format":"","indent":0,"type":"paragraph","version":1}],"direction":"ltr","format":"","indent":0,"type":"root","version":1}}',
      subject: 'Notification'
    }
  ],
  name: 'Notifications 1',
  resources: [
    {
      events: 3,
      extra: {
        events_services: 3
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
    }
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
};


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


export {
  usersResponse, 
  listNotificationResponse,
hostGroupsResponse,
serviceGroupsResponse,
}