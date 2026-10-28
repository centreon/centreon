import Identity from './Hosts.Form.Identity';
import Relations from './Hosts.Form.Relations';
import Settings from './Hosts.Form.Settings';

// The form is split over two files so they run in parallel.
// `ConfigurationBase`'s specs pass `formVariant` explicitly, so this is the
// only place a revert to the modal would turn something red.
describe('Hosts configuration: ', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  describe('Form: ', () => {
    Identity();
    Relations();
    Settings();
  });
});
