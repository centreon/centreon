import Actions from './HostTemplates.Actions';
import Columns from './HostTemplates.Columns';
import Filters from './HostTemplates.Filters';
import Listing from './HostTemplates.Listing';

describe('Host templates: ', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  Listing();
  Columns();
  Filters();
  Actions();
});
