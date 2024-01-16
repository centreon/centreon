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
