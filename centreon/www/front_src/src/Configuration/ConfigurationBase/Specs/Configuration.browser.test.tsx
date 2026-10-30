import { ResourceType } from '../../models';
import Filters from './Configuration.Filters';
import Layout from './Configuration.Layout';
import StaticColumns from './Configuration.StaticColumns';

// Split across three entries to keep each run short: the listing here, the
// actions and the modal in `ConfigurationActions`, the panel in
// `ConfigurationPanel`.
const testCases = [
  { resourceType: ResourceType.Host },
  { resourceType: ResourceType.HostGroup }
];

testCases.forEach(({ resourceType }) => {
  describe(`Configuration: ${resourceType}: `, () => {
    beforeEach(() => {
      expect.hasAssertions();
    });

    Layout(resourceType);
    Filters(resourceType);
    StaticColumns();
  });
});
