import Actions from './HostTemplates.Actions';
import Columns from './HostTemplates.Columns';
import Delete from './HostTemplates.Delete';
import Duplicate from './HostTemplates.Duplicate';
import Filters from './HostTemplates.Filters';
import Listing from './HostTemplates.Listing';
import MassChange from './HostTemplates.MassChange';

describe('Host templates: ', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  Listing();
  Columns();
  Filters();
  Actions();
  Delete();
  Duplicate();
  MassChange();
});
