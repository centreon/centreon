// The mocked API responses.

const creators = [
  'Alice',
  'Charlie',
  'Eve',
  'Grace',
  'Ivy',
  'Kara',
  'Mia',
  'Olivia',
  'Quinn',
  'Steve'
];
const updaters = [
  'Bob',
  'Diana',
  'Frank',
  'Hank',
  'Jack',
  'Leo',
  'Nate',
  'Paul',
  'Rita',
  'Tina'
];

const pad = (value: number): string => String(value).padStart(2, '0');

// Ten connectors, VMWare1 to VMWare10, each one a day older than the previous.
export const getConnectorsResponse = (): object => ({
  meta: { limit: 10, page: 1, search: {}, sort_by: {}, total: 44 },
  result: creators.map((creator, index) => ({
    created_at: `2024-07-${pad(23 - index)}T${pad(10 + index)}:00:00Z`,
    created_by: { id: 2 * index + 1, name: creator },
    description: `Description for VMWare${index + 1}`,
    id: index + 1,
    name: `VMWare${index + 1}`,
    type: 'vmware_v6',
    updated_at: `2024-07-${pad(23 - index)}T${pad(10 + index)}:30:00Z`,
    updated_by: { id: 2 * index + 2, name: updaters[index] }
  }))
});

export const getConnectorResponse = (): object => ({
  created_at: '2024-07-23T10:00:00Z',
  created_by: { id: 1, name: 'Alice' },
  description: 'Description for VMWare1',
  id: 1,
  name: 'VMWare1',
  parameters: {
    port: 443,
    vcenters: [
      {
        id: 1,
        name: 'vCenter1',
        password: 'password1',
        url: 'https://vcenter1.example.com/sdk',
        username: 'user1'
      },
      {
        id: 2,
        name: 'vCenter2',
        password: 'password2',
        url: '192.0.0.1',
        username: 'user2'
      }
    ]
  },
  pollers: [
    { id: 101, name: 'Poller1' },
    { id: 102, name: 'Poller2' }
  ],
  type: 'vmware_v6',
  updated_at: '2024-07-23T10:30:00Z',
  updated_by: { id: 2, name: 'Bob' }
});

export const getPollersResponse = (): object => ({
  meta: { limit: 10, page: 1, search: {}, sort_by: {}, total: 10 },
  result: Array.from({ length: 10 }, (_, index) => ({
    id: index + 1,
    name: `poller${index + 1}`
  }))
});
