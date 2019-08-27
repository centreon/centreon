export default {
  status: true,
  result: {
    pagination: {
      total: 6,
      offset: 0,
      limit: 6,
    },
    entities: [
      {
        id: 1,
        name: 'Null',
        impact: '0',
        color: '#ffffff',
      },
      {
        id: 2,
        name: 'Weak',
        impact: '5',
        color: '#ffeebb',
      },
      {
        id: 3,
        name: 'Minor',
        impact: '25',
        color: '#ffcc77',
      },
      {
        id: 4,
        name: 'Major',
        impact: '50',
        color: '#ff8833',
      },
      {
        id: 5,
        name: 'Critical',
        impact: '75',
        color: '#ff5511',
      },
      {
        id: 6,
        name: 'Blocking',
        impact: '100',
        color: '#ff0000',
      },
    ],
  },
};
