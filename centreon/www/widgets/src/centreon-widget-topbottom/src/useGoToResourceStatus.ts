import { useNavigate } from 'react-router-dom';

import { setUrlQueryParameters } from '@centreon/ui';

const useGoToResourceStatus = (): {
  goToResourceStatusAndOpenPanel: (data) => void;
} => {
  const navigate = useNavigate();

  const openResourceStatusPanel = (data): void => {
    const uuid = data?.uuid;
    const hostId = uuid?.split('-')[0].slice(1);
    const serviceId = uuid?.split('-')[1].slice(1);

    const resourcesDetailsEndpoint = `/centreon/api/latest/monitoring/resources/hosts/${hostId}/services/${serviceId}`;

    const detailsPanel = [
      {
        name: 'details',
        value: {
          id: serviceId,
          resourcesDetailsEndpoint,
          selectedTimePeriodId: 'last_24_h',
          tab: 'details',
          tabParameters: {},
          uuid
        }
      }
    ];

    setUrlQueryParameters(detailsPanel);
  };

  const goToResourceStatus = (data): void => {
    const index = data?.name.indexOf('_');

    const filters = [
      {
        name: 'name',
        value: [
          {
            id: `\\b${data?.name.slice(index + 1)}\\b`,
            name: data?.name.slice(index + 1)
          }
        ]
      },
      {
        name: 'h.name',
        value: [
          {
            id: `\\b${data?.name.slice(0, index)}\\b`,
            name: data?.name.slice(0, index)
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

    const link = `/monitoring/resources?filter=${JSON.stringify(
      filterQueryParameter
    )}&fromTopCounter=true`;

    navigate(link);
  };

  const goToResourceStatusAndOpenPanel = (data): void => {
    goToResourceStatus(data);
    openResourceStatusPanel(data);
  };

  return {
    goToResourceStatusAndOpenPanel
  };
};

export default useGoToResourceStatus;
