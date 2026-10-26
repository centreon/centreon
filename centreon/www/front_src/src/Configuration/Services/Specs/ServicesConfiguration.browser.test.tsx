import Actions from './Services.Actions';
import Columns from './Services.Columns';
import Filters from './Services.Filters';
import Listing from './Services.Listing';
import MassChange from './Services.MassChange';

describe('Services configuration: ', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  Listing();
  Columns();
  Filters();
  Actions();
  // MON-213088
  MassChange();
});
