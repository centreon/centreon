// The API answers the page is tested against.

export const listTokensResponse = {
  meta: {
    limit: 10,
    page: 1,
    search: {},
    sort_by: {
      token_name: 'ASC'
    },
    total: 9
  },
  result: [
    {
      creation_date: '2023-08-31T15:46:00+02:00',
      creator: {
        id: 23,
        name: 'Jane Doe'
      },
      expiration_date: null,
      is_revoked: false,
      name: 'a-token',
      type: 'api',
      user: {
        id: 23,
        name: 'Jane Doe'
      }
    },
    {
      creation_date: '2023-09-31T15:46:00+02:00',
      creator: {
        id: 23,
        name: 'Jane Doe'
      },
      expiration_date: '2028-09-15T00:00:00+00:00',
      is_revoked: true,
      name: 'b-token',
      type: 'cma',
      user: {
        id: 23,
        name: 'Jane Doe'
      }
    },
    {
      creation_date: '2023-08-31T15:46:00+02:00',
      creator: {
        id: 23,
        name: 'Kevin B'
      },
      expiration_date: '2028-08-31T00:00:00+00:00',
      is_revoked: false,
      name: 'c-token',
      type: 'api',
      user: {
        id: 23,
        name: 'Jane Doe'
      }
    },
    {
      creation_date: '2023-08-31T15:46:00+02:00',
      creator: {
        id: 23,
        name: 'Jane Doe'
      },
      expiration_date: '2028-08-31T00:00:00+00:00',
      is_revoked: false,
      name: 'd-token',
      type: 'poller',
      user: {
        id: 23,
        name: 'Jane Doe'
      }
    },
    {
      creation_date: '2023-08-31T15:46:00+02:00',
      creator: {
        id: 23,
        name: 'Jane Doe'
      },
      expiration_date: '2024-08-31T00:00:00+00:00',
      is_revoked: true,
      name: 'e-token',
      type: 'cma',
      user: {
        id: 23,
        name: 'Jane Doe'
      }
    },
    {
      creation_date: '2023-08-31T15:46:00+02:00',
      creator: {
        id: 23,
        name: 'Jammy K'
      },
      expiration_date: '2028-08-31T00:00:00+00:00',
      is_revoked: false,
      name: 'f-token',
      type: 'api',
      user: {
        id: 23,
        name: 'Jane Doe'
      }
    },
    {
      creation_date: '2023-08-31T15:46:00+02:00',
      creator: {
        id: 23,
        name: 'Jane Doe'
      },
      expiration_date: null,
      is_revoked: true,
      name: 'g-token',
      type: 'poller',
      user: {
        id: 23,
        name: 'Jane Doe'
      }
    },
    {
      creation_date: '2023-08-31T15:46:00+02:00',
      creator: {
        id: 23,
        name: 'Jane Doe'
      },
      expiration_date: '2028-08-31T00:00:00+00:00',
      is_revoked: false,
      name: 'h-token',
      type: 'api',
      user: {
        id: 23,
        name: 'Jane Doe'
      }
    },
    {
      creation_date: '2023-08-31T15:46:00+02:00',
      creator: {
        id: 23,
        name: 'Jane Doe'
      },
      expiration_date: '2028-08-31T00:00:00+00:00',
      is_revoked: false,
      name: 'i-token',
      type: 'api',
      user: {
        id: 23,
        name: 'Jane Doe'
      }
    }
  ]
};

// A token whose creator was deleted comes with a null creator id.
export const listTokensWithDeletedCreatorResponse = {
  meta: {
    limit: 10,
    page: 1,
    search: {},
    sort_by: {
      token_name: 'ASC'
    },
    total: 3
  },
  result: [
    {
      creation_date: '2023-08-31T15:46:00+02:00',
      creator: {
        id: 23,
        name: 'Jane Doe'
      },
      expiration_date: null,
      is_revoked: false,
      name: 'a-token',
      type: 'api',
      user: {
        id: 23,
        name: 'Jane Doe'
      }
    },
    {
      creation_date: '2023-08-31T15:46:00+02:00',
      creator: {
        id: null,
        name: 'Deleted Creator'
      },
      expiration_date: '2028-08-31T00:00:00+00:00',
      is_revoked: false,
      name: 'b-token',
      type: 'api',
      user: {
        id: 24,
        name: 'John Doe'
      }
    },
    {
      creation_date: '2023-08-31T15:46:00+02:00',
      creator: {
        id: 23,
        name: 'Kevin B'
      },
      expiration_date: '2028-08-31T00:00:00+00:00',
      is_revoked: false,
      name: 'c-token',
      type: 'api',
      user: {
        id: 23,
        name: 'Jane Doe'
      }
    }
  ]
};

export const listUsersResponse = {
  meta: {
    limit: 10,
    page: 1,
    search: {},
    sort_by: {},
    total: 4
  },
  result: [
    {
      alias: 'admin',
      email: 'admin@centreon.com',
      id: 1,
      is_admin: true,
      name: 'admin admin'
    },
    {
      alias: 'centreon-gorgone',
      email: 'gorgone@localhost',
      id: 4,
      is_admin: true,
      name: 'centreon-gorgone'
    },
    {
      alias: 'guest',
      email: 'guest@localhost',
      id: 17,
      is_admin: false,
      name: 'Guest'
    },
    {
      alias: 'user',
      email: 'user@localhost',
      id: 18,
      is_admin: false,
      name: 'User'
    }
  ]
};

export const tokenDetailsResponse = {
  creation_date: '2023-12-18T16:29:55+01:00',
  creator: {
    id: 1,
    name: 'admin admin'
  },
  expiration_date: '2024-12-18T16:29:55+01:00',
  is_revoked: false,
  name: 'slack',
  token: 'generated-token-value',
  type: 'api',
  user: {
    id: 4,
    name: 'centreon-gorgone'
  }
};
