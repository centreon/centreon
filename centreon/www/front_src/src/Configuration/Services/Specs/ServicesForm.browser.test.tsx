import Form from './Services.Form';
import CheckOptions from './Services.Form.CheckOptions';
import TemplateAndScheduling from './Services.Form.TemplateAndScheduling';

// Apart from the listing specs so the two run in parallel. Each section adds
// its own `Services.Form.<Section>.tsx` file here.
describe('Services configuration: ', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  describe('Form: ', Form);
  describe('Form: ', TemplateAndScheduling);
  describe('Service monitoring properties: ', CheckOptions);
});
