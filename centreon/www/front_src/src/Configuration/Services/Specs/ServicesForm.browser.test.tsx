import Form from './Services.Form';
import CheckOptions from './Services.Form.CheckOptions';
import DataProcessing from './Services.Form.DataProcessing';
import ExtendedInfo from './Services.Form.ExtendedInfo';
import Notifications from './Services.Form.Notifications';
import Relations from './Services.Form.Relations';
import TemplateAndScheduling from './Services.Form.TemplateAndScheduling';

// Apart from the listing specs so the two run in parallel. Each section adds
// its own `Services.Form.<Section>.tsx` file here.
describe('Services configuration: ', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  describe('Form: ', Form);
  describe('Form: ', TemplateAndScheduling);
  describe('Check command / Template / Custom macros: ', CheckOptions);
  describe('Form: ', Relations);
  describe('Form extended info: ', ExtendedInfo);
  describe('Notifications: ', Notifications);
  describe('Form: ', DataProcessing);
});
