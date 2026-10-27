import Actions from './HostTemplates.Actions';
import Form from './HostTemplates.Form';
import Listing from './HostTemplates.Listing';

describe('Host templates configuration: ', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  Listing();
  Actions();
  Form();
});
