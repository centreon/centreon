export const findHostGroupsResponse = {
  meta: {
    limit: 10,
    page: 1,
    search: {},
    sort_by: {},
    total: 4
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

export const findHostCategoriesResponse = {
  meta: {
    limit: 10,
    page: 1,
    search: {},
    sort_by: {},
    total: 2
  },
  result: [
    {
      id: 1,
      name: 'Servers-Paris'
    },
    {
      id: 2,
      name: 'Servers-London'
    }
  ]
};

export const findHostsResponse = {
  meta: {
    limit: 10,
    page: 1,
    search: {},
    sort_by: {},
    total: 3
  },
  result: [
    {
      id: 14,
      name: 'Centreon-Server'
    },
    {
      id: 19,
      name: 'Linux-Server-Paris'
    },
    {
      id: 20,
      name: 'Linux-Server-London'
    }
  ]
};

export const findServiceGroupsResponse = {
  meta: {
    limit: 10,
    page: 1,
    search: {},
    sort_by: {},
    total: 2
  },
  result: [
    {
      id: 1,
      name: 'Linux-Servers-Services'
    },
    {
      id: 2,
      name: 'Windows-Servers-Services'
    }
  ]
};

export const findServiceCategoriesResponse = {
  meta: {
    limit: 10,
    page: 1,
    search: {},
    sort_by: {},
    total: 4
  },
  result: [
    {
      id: 1,
      name: 'Ping'
    },
    {
      id: 2,
      name: 'Traffic'
    },
    {
      id: 3,
      name: 'Disk'
    },
    {
      id: 4,
      name: 'Memory'
    }
  ]
};

export const findServicesResponse = {
  meta: {
    limit: 10,
    page: 1,
    search: {},
    sort_by: {},
    total: 8
  },
  result: [
    {
      id: 19,
      name: 'Disk-/'
    },
    {
      id: 20,
      name: 'Disk-/home'
    },
    {
      id: 21,
      name: 'Disk-/opt'
    },
    {
      id: 22,
      name: 'Disk-/usr'
    },
    {
      id: 23,
      name: 'Disk-/var'
    },
    {
      id: 24,
      name: 'Load'
    },
    {
      id: 25,
      name: 'Memory'
    },
    {
      id: 26,
      name: 'Ping'
    }
  ]
};

export const findMetaServicesResponse = {
  meta: {
    limit: 10,
    page: 1,
    search: {},
    sort_by: {},
    total: 4
  },
  result: [
    {
      id: 1,
      name: 'META_SERVICE_PING_PARIS'
    },
    {
      id: 2,
      name: 'META_SERVICE_MEMORY_PARIS'
    },
    {
      id: 3,
      name: 'META_SERVICE_PING_LONDON'
    },
    {
      id: 4,
      name: 'META_SERVICE_MEMORY_LONDON'
    }
  ]
};

export const findContactsResponse = {
  meta: {
    limit: 10,
    page: 1,
    search: {},
    sort_by: {},
    total: 2
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

export const findContactGroupsResponse = {
  meta: {
    limit: 10,
    page: 1,
    search: {},
    sort_by: {},
    total: 2
  },
  result: [
    {
      id: 3,
      name: 'Guest'
    },
    {
      id: 5,
      name: 'Supervisors'
    }
  ]
};

export const formData = {
  contact_groups: {
    all: false,
    ids: [5]
  },
  contacts: {
    all: false,
    ids: [4]
  },
  dataset_filters: [
    {
      dataset_filter: {
        dataset_filter: null,
        resources: [14],
        type: 'host'
      },
      resources: [53],
      type: 'hostgroup'
    },
    {
      dataset_filter: null,
      resources: [1],
      type: 'service_category'
    }
  ],
  description: 'rule#1: Lorem ipsum...',
  is_enabled: true,
  name: 'rule#1'
};

export const formDataWithAllHostGroups = {
  contact_groups: {
    all: false,
    ids: [5]
  },
  contacts: {
    all: false,
    ids: [4]
  },
  dataset_filters: [
    {
      dataset_filter: null,
      resources: [],
      type: 'hostgroup'
    }
  ],
  description: 'rule#1: Lorem ipsum...',
  is_enabled: true,
  name: 'rule#1'
};

export const allResourcesFormData = {
  contact_groups: {
    all: false,
    ids: [5]
  },
  contacts: {
    all: false,
    ids: [4]
  },
  dataset_filters: [
    {
      dataset_filter: null,
      resources: [],
      type: 'all'
    }
  ],
  description: 'rule#0: Lorem ipsum...',
  is_enabled: true,
  name: 'rule#0'
};

export const formDataWithAllContacts = {
  contact_groups: {
    all: false,
    ids: []
  },
  contacts: {
    all: true,
    ids: []
  },
  dataset_filters: [
    {
      dataset_filter: null,
      resources: [],
      type: 'hostgroup'
    }
  ],
  description: 'rule#1: Lorem ipsum...',
  is_enabled: true,
  name: 'rule#1'
};

export const formDataWithAllContactGroups = {
  contact_groups: {
    all: true,
    ids: []
  },
  contacts: {
    all: false,
    ids: []
  },
  dataset_filters: [
    {
      dataset_filter: null,
      resources: [],
      type: 'hostgroup'
    }
  ],
  description: 'rule#1: Lorem ipsum...',
  is_enabled: true,
  name: 'rule#1'
};

export const editedRuleFormData = {
  contact_groups: {
    all: false,
    ids: [3, 5]
  },
  contacts: {
    all: false,
    ids: [1, 4]
  },
  dataset_filters: [
    {
      dataset_filter: null,
      resources: [],
      type: 'hostgroup'
    }
  ],
  description: 'resourceAccessRule1',
  is_enabled: true,
  name: 'rule#1'
};

export const editedRuleFormDataWithAllContactsAndContactGroups = {
  contact_groups: {
    all: true,
    ids: []
  },
  contacts: {
    all: true,
    ids: []
  },
  dataset_filters: [
    {
      dataset_filter: null,
      resources: [],
      type: 'hostgroup'
    }
  ],
  description: 'resourceAccessRule1',
  is_enabled: true,
  name: 'rule#1'
};

export const findResourceAccessRuleResponse = (): object => ({
  contact_groups: {
    all: false,
    values: [
      { id: 3, name: 'Guest' },
      { id: 5, name: 'Supervisor' }
    ]
  },
  contacts: {
    all: false,
    values: [
      { id: 1, name: 'admin admin' },
      { id: 4, name: 'centreon-gorgone' }
    ]
  },
  dataset_filters: [
    {
      dataset_filter: {
        dataset_filter: null,
        resources: [{ id: 14, name: 'Centreon-Server' }],
        type: 'host'
      },
      resources: [{ id: 53, name: 'Linux-Servers' }],
      type: 'hostgroup'
    },
    {
      dataset_filter: null,
      resources: [
        { id: 23, name: 'Disk-/var' },
        { id: 22, name: 'Disk-/usr' },
        { id: 21, name: 'Disk-/opt' },
        { id: 19, name: 'Disk-/' }
      ],
      type: 'service'
    }
  ],
  description: 'resourceAccessRule1',
  id: 1,
  is_enabled: true,
  name: 'rule1'
});

export const findResourceAccessRuleResponseDecoded = (): object => ({
  contactGroups: {
    all: false,
    values: [
      { id: 3, name: 'Guest' },
      { id: 5, name: 'Supervisor' }
    ]
  },
  contacts: {
    all: false,
    values: [
      { id: 1, name: 'admin admin' },
      { id: 4, name: 'centreon-gorgone' }
    ]
  },
  datasetFilters: [
    {
      datasetFilter: {
        datasetFilter: null,
        resourceType: 'host',
        resources: [{ id: 14, name: 'Centreon-Server' }]
      },
      resourceType: 'hostgroup',
      resources: [{ id: 53, name: 'Linux-Servers' }]
    },
    {
      datasetFilter: null,
      resourceType: 'service',
      resources: [
        { id: 23, name: 'Disk-/var' },
        { id: 22, name: 'Disk-/usr' },
        { id: 21, name: 'Disk-/opt' },
        { id: 19, name: 'Disk-/' }
      ]
    }
  ],
  description: 'resourceAccessRule1',
  id: 1,
  isActivated: true,
  name: 'rule1'
});

export const platformVersions = {
  isCloudPlatform: true,
  modules: {
    'centreon-bam-server': {
      fix: '0',
      major: '24',
      minor: '04',
      version: '24.04.0'
    }
  },
  web: {
    fix: '0',
    major: '24',
    minor: '04',
    version: '24.04.0'
  },
  widgets: {}
};

export const findBusinessViewsResponse = {
  meta: {
    limit: 10,
    page: 1,
    search: {},
    sort_by: {},
    total: 2
  },
  result: [
    {
      id: 1,
      name: 'BV1'
    },
    {
      id: 2,
      name: 'BV2'
    }
  ]
};

export const formDataWithBusinessViews = {
  contact_groups: {
    all: false,
    ids: [5]
  },
  contacts: {
    all: false,
    ids: [4]
  },
  dataset_filters: [
    {
      dataset_filter: null,
      resources: [1, 2],
      type: 'business_view'
    }
  ],
  description: 'rule#1: Lorem ipsum...',
  is_enabled: true,
  name: 'rule#1'
};

export const formDataWithAllBusinessViews = {
  contact_groups: {
    all: false,
    ids: [5]
  },
  contacts: {
    all: false,
    ids: [4]
  },
  dataset_filters: [
    {
      dataset_filter: null,
      resources: [],
      type: 'business_view'
    }
  ],
  description: 'rule#1: Lorem ipsum...',
  is_enabled: true,
  name: 'rule#1'
};

export const editedRuleFormDataiWithBusinessViews = {
  contact_groups: {
    all: false,
    ids: [3, 5]
  },
  contacts: {
    all: false,
    ids: [1, 4]
  },
  dataset_filters: [
    {
      dataset_filter: null,
      resources: [1, 2],
      type: 'business_view'
    }
  ],
  description: 'resourceAccessRule1',
  is_enabled: true,
  name: 'rule#1'
};

export const editedRuleFormDataiWithAllBusinessViews = {
  contact_groups: {
    all: false,
    ids: [3, 5]
  },
  contacts: {
    all: false,
    ids: [1, 4]
  },
  dataset_filters: [
    {
      dataset_filter: null,
      resources: [],
      type: 'business_view'
    }
  ],
  description: 'resourceAccessRule1',
  is_enabled: true,
  name: 'rule#1'
};
