import { equals } from 'ramda';

import { Resource, ResourceCategory, ResourceType } from '../../../../models';

interface CheckedResources {
  resources: Array<Resource>;
}

interface Parent {
  id: number;
}

interface PayloadCheckedResource {
  id?: number;
  parent: Parent | null;
  type: ResourceCategory;
}

export const adjustedCheckedResources = ({
  resources
}: CheckedResources): Array<PayloadCheckedResource> => {
  const payload = resources.map(({ type, id, parent, service_id }) => ({
    id: equals(type, ResourceType.anomalyDetection) ? service_id : id,
    parent: parent ? { id: parent?.id } : null,
    type: ResourceCategory[type]
  }));

  return payload;
};
