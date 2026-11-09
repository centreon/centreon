import ExtendedInfosAndTemplates from './Hosts.Form.ExtendedInfosAndTemplates';
import Macros from './Hosts.Form.Macros';

// The rest of the form, in a file of its own so it runs in parallel with
// HostsForm.browser.test.tsx.
describe('Hosts configuration: ', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  describe('Form: ', () => {
    ExtendedInfosAndTemplates();
    Macros();
  });
});
