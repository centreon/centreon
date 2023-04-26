import buildListingEndpoint from '.';

describe(buildListingEndpoint, () => {
  const baseEndpoint = 'resources';
  const parameters = {
    limit: 10,
    page: 1,
    sort: { name: 'asc' }
  };
  it('builds the listing endpoint string using the given params', () => {
    const endpoint = buildListingEndpoint({ baseEndpoint, parameters });

    expect(decodeURIComponent(endpoint)).toEqual(
      'resources?page=1&limit=10&sort_by={"name":"asc"}'
    );
  });

  it('builds the listing endpoint string with a "$and" search expression between search options when search option patterns are found in the search input', () => {
    const endpoint = buildListingEndpoint({
      baseEndpoint,
      parameters: {
        ...parameters,
        search: {
          regex: {
            fields: ['h.name'],
            value: 'h.name:hvalue'
          }
        }
      }
    });

    expect(decodeURIComponent(endpoint)).toContain(
      '&search={"$and":[{"h.name":{"$rg":"hvalue"}}]}'
    );
  });

  it('builds the listing endpoint string with a "$or" search expression between search options when search option patterns are not found in the search input', () => {
    const endpoint = buildListingEndpoint({
      baseEndpoint,
      parameters: {
        ...parameters,
        search: {
          regex: {
            fields: ['h.name', 's.description'],
            value: 'searchValue'
          }
        }
      }
    });

    expect(decodeURIComponent(endpoint)).toContain(
      '&search={"$or":[{"h.name":{"$rg":"searchValue"}},{"s.description":{"$rg":"searchValue"}}]}'
    );
  });

  it('builds the listing endpoint string with a "$and" search expression between strings list search options', () => {
    const endpoint = buildListingEndpoint({
      baseEndpoint,
      parameters: {
        ...parameters,
        search: {
          lists: [
            {
              field: 'h.status',
              values: ['OK']
            }
          ]
        }
      }
    });

    expect(decodeURIComponent(endpoint)).toContain(
      '&search={"$and":[{"h.status":{"$in":["OK"]}}]}'
    );
  });

  it('builds the listing endpoint string with a "$and" search expression between numbers list search options', () => {
    const endpoint = buildListingEndpoint({
      baseEndpoint,
      parameters: {
        ...parameters,
        search: {
          lists: [
            {
              field: 'h.status',
              values: [1, 2, 3, 4]
            }
          ]
        }
      }
    });

    expect(decodeURIComponent(endpoint)).toContain(
      '&search={"$and":[{"h.status":{"$in":[1,2,3,4]}}]}'
    );
  });

  it('build the listing endpoint with a "$and" search expression between the given conditions', () => {
    const endpointWithValues = buildListingEndpoint({
      baseEndpoint,
      parameters: {
        ...parameters,
        search: {
          conditions: [
            {
              field: 'date',
              values: {
                $gt: '2020-12-01T07:00:00',
                $lt: '2020-12-01T11:00:00'
              }
            }
          ]
        }
      }
    });

    expect(decodeURIComponent(endpointWithValues)).toContain(
      '&search={"$and":[{"date":{"$gt":"2020-12-01T07:00:00"}},{"date":{"$lt":"2020-12-01T11:00:00"}}]}'
    );

    const endpointWithValue = buildListingEndpoint({
      baseEndpoint,
      parameters: {
        ...parameters,
        search: {
          conditions: [
            {
              field: 'is_activated',
              value: true
            }
          ]
        }
      }
    });

    expect(decodeURIComponent(endpointWithValue)).toContain(
      '&search={"$and":[{"is_activated":true}]}'
    );
  });
});
