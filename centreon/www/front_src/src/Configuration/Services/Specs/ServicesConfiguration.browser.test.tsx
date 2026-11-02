import Actions from './Services.Actions';
import Columns from './Services.Columns';
import Filters from './Services.Filters';
import Listing from './Services.Listing';

describe('Services configuration: ', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  Listing();
  Columns();
  Filters();
  Actions();
});
