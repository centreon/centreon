import Form from './Services.Form';
import Notifications from './Services.Form.Notifications';
import FormServiceConfiguration from './Services.Form.ServiceConfiguration';

// Apart from the listing specs so the two run in parallel. Each section adds
// its own `Services.Form.<Section>.tsx` file here.
describe('Services configuration: ', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  describe('Form: ', () => {
    Form();
  });

  describe('Form, Service Configuration: ', () => {
    FormServiceConfiguration();
  });
  describe('Form, Notifications section: ', Notifications);
});
