import { ResourceType } from '../../models';

import Actions from './Configuration.Actions';
import Filters from './Configuration.Filters';
import Layout from './Configuration.Layout';
import Modal from './Configuration.Modal';

const testCases = [
  { resourceType: ResourceType.Host },
  { resourceType: ResourceType.HostGroup },
  { resourceType: ResourceType.ServiceGroup }
];

testCases.forEach(({ resourceType }) => {
  describe(`Configuration ( resource type: ${resourceType} )`, () => {
    Layout(resourceType);
    Filters(resourceType);
    Actions(resourceType);
    Modal(resourceType);
  });
});
