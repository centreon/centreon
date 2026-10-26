import Form from './Services.Form';
import CheckOptions from './Services.Form.CheckOptions';
import FormDataProcessing from './Services.Form.DataProcessing';
import ExtendedInfo from './Services.Form.ExtendedInfo';
import Notifications from './Services.Form.Notifications';
import FormRelations from './Services.Form.Relations';
import FormServiceConfiguration from './Services.Form.ServiceConfiguration';

// Apart from the listing specs so the two run in parallel. Each section adds
// its own `Services.Form.<Section>.tsx` file here.
describe('Services configuration: ', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  describe('Form: ', () => {
    Form();
    // MON-213084
    FormRelations();
    FormDataProcessing();
  });

  describe('Form, Service Configuration: ', () => {
    FormServiceConfiguration();
  });
  describe('Form, Notifications section: ', Notifications);
  describe('Form, Extended Info: ', ExtendedInfo);
  describe('Check options: ', CheckOptions);
});
