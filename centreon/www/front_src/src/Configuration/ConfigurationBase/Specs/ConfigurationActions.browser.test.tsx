import { ResourceType } from '../../models';
import Actions from './Configuration.Actions';
import Modal from './Configuration.Modal';

const testCases = [
  { resourceType: ResourceType.Host },
  { resourceType: ResourceType.HostGroup }
];

testCases.forEach(({ resourceType }) => {
  describe(`Configuration: ${resourceType}: `, () => {
    beforeEach(() => {
      expect.hasAssertions();
    });

    Actions(resourceType);
    Modal(resourceType);
  });
});
