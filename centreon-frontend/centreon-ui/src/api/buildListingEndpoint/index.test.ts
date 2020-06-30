import buildListingEndpoint from '.';

describe(buildListingEndpoint, () => {
  const baseEndpoint = 'resources';
  const params = {
    page: 1,
    limit: 10,
    sort: { name: 'asc' },
  };
  it('builds the listing endpoint string using the given params', () => {
    const endpoint = buildListingEndpoint({ baseEndpoint, params });

    expect(endpoint).toEqual(
      'resources?page=1&limit=10&sort_by={"name":"asc"}',
    );
  });

  it('builds the listing endpoint string with a "$and" search expression between search options when search option patterns are found in the search input', () => {
    const endpoint = buildListingEndpoint({
      baseEndpoint,
      params: {
        ...params,
        search: 'h.name:hvalue',
        searchOptions: ['h.name'],
      },
    });

    expect(endpoint).toContain(
      '&search={"$and":[{"h.name":{"$rg":"hvalue"}}]}',
    );
  });

  it('builds the listing endpoint string with a "$or" search expression between search options when search option patterns are not found in the search input', () => {
    const endpoint = buildListingEndpoint({
      baseEndpoint,
      params: {
        ...params,
        search: 'searchvalue',
        searchOptions: ['h.name', 's.description'],
      },
    });

    expect(endpoint).toContain(
      '&search={"$or":[{"h.name":{"$rg":"searchvalue"}},{"s.description":{"$rg":"searchvalue"}}]}',
    );
  });
});
