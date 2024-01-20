import { centreonBaseURL } from '@centreon/ui';

interface UseLinkToResourceStatus {
  getResourcesStatusUrl: (data) => string;
}

const useLinkToResourceStatus = (): UseLinkToResourceStatus => {
  const getDetailsPanelQueriers = (data): object => {
    const uuid = data?.uuid;
    const hostId = uuid?.split('-')[0]?.slice(1);
    const serviceId = uuid?.split('-')[1]?.slice(1);

    const resourcesDetailsEndpoint = `${centreonBaseURL}/api/latest/monitoring/resources/hosts/${hostId}/services/${serviceId}`;

    const queryParameters = {
      id: serviceId,
      resourcesDetailsEndpoint,
      selectedTimePeriodId: 'last_24_h',
      tab: 'details',
      tabParameters: {},
      uuid
    };

    return queryParameters;
  };

  const getResourcesStatusUrl = (data): string => {
    const filters = [
      {
        name: 'name',
        value: [
          {
            id: `\\b${data?.name}\\b`,
            name: data?.name
          }
        ]
      },
      {
        name: 'h.name',
        value: [
          {
            id: `\\b${data?.parentName}\\b`,
            name: data?.parentName
          }
        ]
      }
    ];

    const serviceCriteria = {
      name: 'resource_types',
      value: [{ id: 'service', name: 'Service' }]
    };

    const filterQueryParameter = {
      criterias: [serviceCriteria, ...filters, { name: 'search', value: '' }]
    };

    const encodedFilterParams = encodeURIComponent(
      JSON.stringify(filterQueryParameter)
    );

    const detailsPanelQueriers = getDetailsPanelQueriers(data);
    const encodedDetailsParams = encodeURIComponent(
      JSON.stringify(detailsPanelQueriers)
    );

    return `/monitoring/resources?details=${encodedDetailsParams}&filter=${encodedFilterParams}&fromTopCounter=true`;
  };

  return {
    getResourcesStatusUrl
  };
};

export default useLinkToResourceStatus;
