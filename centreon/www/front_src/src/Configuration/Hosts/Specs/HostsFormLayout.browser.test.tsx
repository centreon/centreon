import Layout from './Hosts.Form.Layout';

// Where the form shows each field, in a file of its own so it runs in
// parallel with the other form specs.
describe('Hosts configuration: ', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  describe('Form: ', () => {
    Layout();
  });
});
