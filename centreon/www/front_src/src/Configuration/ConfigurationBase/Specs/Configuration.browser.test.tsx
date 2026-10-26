import { ResourceType } from '../../models';
import Filters from './Configuration.Filters';
import Layout from './Configuration.Layout';
import StaticColumns from './Configuration.StaticColumns';

// The Cypress spec ran every area for each resource type in one file; it is
// split in three entries, each composing a subset of the areas under the same
// names: the listing here, the actions and the modal in
// `ConfigurationActions`, the panel in `ConfigurationPanel`.
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
