import Actions from './Hosts.Actions';
import Columns from './Hosts.Columns';
import Filters from './Hosts.Filters';
import Listing from './Hosts.Listing';
import MassChange from './Hosts.MassChange';

describe('Hosts configuration: ', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  Listing();
  Columns();
  Filters();
  Actions();
  MassChange();
});
