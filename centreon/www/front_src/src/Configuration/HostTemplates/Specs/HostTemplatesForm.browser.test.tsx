import Form from './HostTemplates.Form';
import MonitoringProperties from './HostTemplates.Form.MonitoringProperties';
import ParentTemplates from './HostTemplates.Form.ParentTemplates';

// Apart from the listing specs so the two run in parallel. Each section adds
// its own `HostTemplates.Form.<Section>.tsx` file here.
describe('Host templates configuration: ', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  describe('Form: ', Form);
  describe('Form: ', MonitoringProperties);
  describe('Form: ', ParentTemplates);
});
