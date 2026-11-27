import Form from './HostTemplates.Form';
import Advanced from './HostTemplates.Form.Advanced';
import MonitoringEngine from './HostTemplates.Form.MonitoringEngine';
import MonitoringProperties from './HostTemplates.Form.MonitoringProperties';
import Notifications from './HostTemplates.Form.Notifications';
import ParentTemplates from './HostTemplates.Form.ParentTemplates';
import Relations from './HostTemplates.Form.Relations';

// Apart from the listing specs so the two run in parallel. Each section adds
// its own `HostTemplates.Form.<Section>.tsx` file here.
describe('Host templates configuration: ', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  describe('Form: ', Form);
  describe('Form: ', MonitoringProperties);
  describe('Form: ', ParentTemplates);
  describe('Form: ', Relations);
  describe('Form: ', MonitoringEngine);
  describe('Form: ', Notifications);
  describe('Form: ', Advanced);
});
