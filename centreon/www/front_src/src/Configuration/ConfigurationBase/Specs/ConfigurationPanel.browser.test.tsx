import { ResourceType } from '../../models';
import Panel from './Configuration.Panel';

const testCases = [
  { resourceType: ResourceType.Host },
  { resourceType: ResourceType.HostGroup }
];

testCases.forEach(({ resourceType }) => {
  describe(`Configuration: ${resourceType}: `, () => {
    beforeEach(() => {
      expect.hasAssertions();
    });

    Panel(resourceType);
  });
});
