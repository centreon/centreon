export const data = JSON.parse(
  JSON.stringify({
    id: 1,
    is_activated: true,
    messages: [
      {
        channel: 'mail',
        message: 'blblabla',
        subject: 'blblabla'
      }
    ],
    name: 'blablabla',
    resources: [
      {
        events: ['up', 'down'],
        extra: {
          events_services: ['ok', 'warning']
        },
        ids: [
          {
            id: 1,
            name: ''
          }
        ],
        type: 'hostgroup'
      },
      {
        events: ['ok', 'warning'],
        ids: [
          {
            id: 1,
            name: ''
          }
        ],
        type: 'servicegroup'
      },
      {
        events: ['ok', 'warning'],
        ids: [
          {
            id: 1,
            name: ''
          }
        ],
        type: 'businessview'
      }
    ],
    timeperiod: {
      id: 1,
      name: ''
    },
    users: [
      {
        id: 1,
        name: ''
      }
    ]
  })
);
