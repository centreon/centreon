import { ResourceType } from '../../models';

import Actions from './Configuration.Actions';
import Filters from './Configuration.Filters';
import Layout from './Configuration.Layout';
import Modal from './Configuration.Modal';

const testCases = [
  { resourceType: ResourceType.Host },
  { resourceType: ResourceType.HostGroup }
];

testCases.forEach(({ resourceType }) => {
  describe(`Configuration: ${resourceType}: `, () => {
    Layout(resourceType);
    Filters(resourceType);
    Actions(resourceType);
    Modal(resourceType);
  });
});
