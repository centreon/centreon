import { useEffect, useRef } from 'react';

import { useAtom, useAtomValue, useSetAtom } from 'jotai';
import {
  always,
  equals,
  ifElse,
  isEmpty,
  isNil,
  map,
  mergeRight,
  not,
  pathEq,
  pathOr,
  prop
} from 'ramda';
import { useTranslation } from 'react-i18next';

import type { SelectEntry } from '@centreon/ui';
import {
  getData,
  getFoundFields,
  getUrlQueryParameters,
  useRequest
} from '@centreon/ui';
import { refreshIntervalAtom } from '@centreon/ui-context';

import { selectedVisualizationAtom } from '../../Actions/actionsAtoms';
import {
  clearSelectedResourceDerivedAtom,
  detailsAtom,
  selectedResourceDetailsEndpointDerivedAtom,
  selectedResourceUuidAtom,
  selectedResourcesDetailsAtom,
  sendingDetailsAtom
} from '../../Details/detailsAtoms';
import { ResourceDetails } from '../../Details/models';
import { searchableFields } from '../../Filter/Criterias/searchQueryLanguage';
import {
  appliedFilterAtom,
  customFiltersAtom,
  getCriteriaValueDerivedAtom
} from '../../Filter/filterAtoms';
import {
  resourcesEndpoint as allResourcesEndpoint,
  hostsEndpoint
} from '../../api/endpoint';
import { ResourceListing, SortOrder, Visualization } from '../../models';
import {
  labelNoResourceFound,
  labelSomethingWentWrong
} from '../../translatedLabels';
import { listResources } from '../api';
import {
  enabledAutorefreshAtom,
  limitAtom,
  listingAtom,
  pageAtom,
  sendingAtom
} from '../listingAtoms';
import { resourceDetailsDecoder } from '../../decoders';

import { Search } from './models';

export interface LoadResources {
  initAutorefreshAndLoad: () => void;
}

const secondSortField = 'last_status_change';
const defaultSecondSortCriteria = { [secondSortField]: SortOrder.desc };

const useLoadResources = (): LoadResources => {
  const { t } = useTranslation();

  const { sendRequest, sending } = useRequest<ResourceListing>({
    getErrorMessage: ifElse(
      pathEq(404, ['response', 'status']),
      always(t(labelNoResourceFound)),
      pathOr(t(labelSomethingWentWrong), ['response', 'data', 'message'])
    ),
    request: listResources
  });

  const { sendRequest: sendLoadDetailsRequest, sending: sendingDetails } =
    useRequest<ResourceDetails>({
      decoder: resourceDetailsDecoder,
      getErrorMessage: ifElse(
        pathEq(404, ['response', 'status']),
        always(t(labelNoResourceFound)),
        pathOr(t(labelSomethingWentWrong), ['response', 'data', 'message'])
      ),
      request: getData
    });

  const [page, setPage] = useAtom(pageAtom);
  const [details, setDetails] = useAtom(detailsAtom);
  const refreshInterval = useAtomValue(refreshIntervalAtom);
  const selectedResourceUuid = useAtomValue(selectedResourceUuidAtom);
  const limit = useAtomValue(limitAtom);
  const enabledAutorefresh = useAtomValue(enabledAutorefreshAtom);
  const selectedResourceDetailsEndpoint = useAtomValue(
    selectedResourceDetailsEndpointDerivedAtom
  );
  const selectedResourceDetails = useAtomValue(selectedResourcesDetailsAtom);
  const customFilters = useAtomValue(customFiltersAtom);
  const getCriteriaValue = useAtomValue(getCriteriaValueDerivedAtom);
  const appliedFilter = useAtomValue(appliedFilterAtom);
  const visualization = useAtomValue(selectedVisualizationAtom);
  const setListing = useSetAtom(listingAtom);
  const setSending = useSetAtom(sendingAtom);
  const setSendingDetails = useSetAtom(sendingDetailsAtom);
  const clearSelectedResource = useSetAtom(clearSelectedResourceDerivedAtom);
  const refreshIntervalRef = useRef<number>();

  const refreshIntervalMs = refreshInterval * 1000;

  const resourcesEndpoint = equals(visualization, Visualization.Host)
    ? hostsEndpoint
    : allResourcesEndpoint;

  const getSort = (): { [sortField: string]: SortOrder } | undefined => {
    const sort = getCriteriaValue('sort');

    if (isNil(sort)) {
      return undefined;
    }

    const [sortField, sortOrder] = sort as [string, SortOrder];

    const secondSortCriteria =
      not(equals(sortField, secondSortField)) && defaultSecondSortCriteria;

    return {
      [sortField]: sortOrder,
      ...secondSortCriteria
    };
  };

  const loadDetails = (): void => {
    if (isNil(selectedResourceDetails?.resourceId)) {
      return;
    }

    sendLoadDetailsRequest({
      // endpoint: selectedResourceDetailsEndpoint,
      endpoint:
        'http://localhost:3000/api/latest/monitoring/resources/anomaly-detection/4'
    })
      .then(setDetails)
      .catch(() => {
        clearSelectedResource();
      });
  };

  const getSearch = (): Search | undefined => {
    const searchCriteria = getCriteriaValue('search');

    if (!searchCriteria) {
      return undefined;
    }

    const fieldMatches = getFoundFields({
      fields: searchableFields,
      value: searchCriteria as string
    });

    if (!isEmpty(fieldMatches)) {
      const matches = fieldMatches.map((item) => {
        const field = item?.field;
        const values = item.value?.split(',')?.join('|');

        return { field, value: `${field}:${values}` };
      });

      const formattedValue = matches.reduce((accumulator, previousValue) => {
        return {
          ...accumulator,
          value: `${accumulator.value} ${previousValue.value}`
        };
      });

      return {
        regex: {
          fields: matches.map(({ field }) => field),
          value: formattedValue.value
        }
      };
    }

    return {
      regex: {
        fields: searchableFields,
        value: searchCriteria as string
      }
    };
  };

  const load = (): void => {
    const getCriteriaIds = (
      name: string
    ): Array<string | number> | undefined => {
      const criteriaValue = getCriteriaValue(name) as
        | Array<SelectEntry>
        | undefined;

      return criteriaValue?.map(prop('id'));
    };

    const getCriteriaNames = (name: string): Array<string> => {
      const criteriaValue = getCriteriaValue(name) as
        | Array<SelectEntry>
        | undefined;

      return (criteriaValue || []).map(prop('name')) as Array<string>;
    };

    const getCriteriaLevels = (name: string): Array<number> => {
      const criteriaValue = getCriteriaValue(name) as
        | Array<SelectEntry>
        | undefined;

      const results = criteriaValue?.map(prop('name'));

      return results?.map((item) => Number(item)) as Array<number>;
    };

    if (getUrlQueryParameters().fromTopCounter) {
      return;
    }

    const names = getCriteriaNames('names');
    const parentNames = getCriteriaNames('parent_names');

    sendRequest({
      endpoint: resourcesEndpoint,
      hostCategories: getCriteriaNames('host_categories'),
      hostGroups: getCriteriaNames('host_groups'),
      hostSeverities: getCriteriaNames('host_severities'),
      hostSeverityLevels: getCriteriaLevels('host_severity_levels'),
      limit,
      monitoringServers: getCriteriaNames('monitoring_servers'),
      page,
      resourceTypes: getCriteriaIds('resource_types'),
      search: mergeRight(getSearch() || {}, {
        conditions: [
          ...names.map((name) => ({
            field: 'name',
            values: {
              $rg: name
            }
          })),
          ...parentNames.map((name) => ({
            field: 'parent_name',
            values: {
              $rg: name
            }
          }))
        ]
      }),
      serviceCategories: getCriteriaNames('service_categories'),
      serviceGroups: getCriteriaNames('service_groups'),
      serviceSeverities: getCriteriaNames('service_severities'),
      serviceSeverityLevels: getCriteriaLevels('service_severity_levels'),
      sort: getSort(),
      states: getCriteriaIds('states'),
      statusTypes: getCriteriaIds('status_types'),
      statuses: getCriteriaIds('statuses')
    }).then((response) => {
      if (!equals(visualization, Visualization.Host)) {
        setListing({
          ...response,
          result: [
            ...response.result,
            {
              alias: null,
              duration: '2h 22m',
              fqdn: null,
              has_active_checks_enabled: true,
              has_passive_checks_enabled: true,
              host_id: 197,
              icon: null,
              id: 4,
              information: 'OK: Regular activity, connection=159.00',
              is_acknowledged: false,
              is_in_downtime: false,
              is_notification_enabled: false,
              last_check: '15m 30s',
              last_status_change: '2024-07-10T10:07:40+02:00',
              links: {
                endpoints: {
                  acknowledgement:
                    '/centreon/api/latest/monitoring/hosts/197/services/2911/acknowledgements?limit=1',
                  check: null,
                  details:
                    '/centreon/api/latest/monitoring/resources/anomaly-detection/4',
                  downtime:
                    '/centreon/api/latest/monitoring/hosts/197/services/2911/downtimes?search=%7B%22%24and%22:%5B%7B%22start_time%22:%7B%22%24lt%22:1720607426%7D,%22end_time%22:%7B%22%24gt%22:1720607426%7D,%220%22:%7B%22%24or%22:%7B%22is_cancelled%22:%7B%22%24neq%22:1%7D,%22deletion_time%22:%7B%22%24gt%22:1720607426%7D%7D%7D%7D%5D%7D',
                  forced_check: null,
                  metrics: null,
                  performance_graph:
                    '/centreon/api/latest/monitoring/hosts/197/services/2911/metrics/performance',
                  status_graph:
                    '/centreon/api/latest/monitoring/hosts/197/services/2911/metrics/status',
                  timeline:
                    '/centreon/api/latest/monitoring/hosts/197/services/2911/timeline'
                },
                externals: {
                  action_url: '',
                  notes: {
                    label: '',
                    url: ''
                  }
                },
                uris: {
                  configuration: null,
                  logs: null,
                  reporting: null
                }
              },
              monitoring_server_name: 'Central',
              name: 'anomaly-nbr-connect',
              parent: {
                alias: 'fw-brasilia',
                fqdn: 'fw-brasilia',
                id: 197,
                monitoring_server_name: null,
                name: 'fw-brasilia',
                short_type: 'h',
                status: {
                  code: 0,
                  name: 'UP',
                  severity_code: 5
                },
                type: 'host',
                uuid: 'h197'
              },
              performance_data: null,
              service_id: 2911,
              severity: null,
              short_type: 'a',
              status: {
                code: 0,
                name: 'OK',
                severity_code: 5
              },
              tries: '1/4 (H)',
              type: 'anomaly-detection',
              uuid: 'h197-a4'
            }
          ]
        });

        return;
      }

      const result = map((item) => {
        return {
          ...item,
          children: item?.children.resources,
          childrenCount: item?.children.status_count
        };
      }, response.result);

      const hostsResponse = { ...response, result };

      setListing(hostsResponse);
    });

    if (isNil(details)) {
      return;
    }

    loadDetails();
  };

  const initAutorefresh = (): void => {
    window.clearInterval(refreshIntervalRef.current);

    const interval = enabledAutorefresh
      ? window.setInterval(() => {
          load();
        }, refreshIntervalMs)
      : undefined;

    refreshIntervalRef.current = interval;
  };

  const initAutorefreshAndLoad = (): void => {
    if (isNil(customFilters)) {
      return;
    }

    initAutorefresh();
    load();
  };

  useEffect(() => {
    initAutorefresh();
  }, [enabledAutorefresh, selectedResourceDetails?.resourceId]);

  useEffect(() => {
    return (): void => {
      clearInterval(refreshIntervalRef.current);
    };
  }, []);

  useEffect(() => {
    if (isNil(details)) {
      return;
    }

    initAutorefresh();
  }, [isNil(details)]);

  useEffect(() => {
    if (isNil(page)) {
      return;
    }

    initAutorefreshAndLoad();
  }, [page]);

  useEffect(() => {
    if (page === 1) {
      initAutorefreshAndLoad();
    }

    setPage(1);
  }, [limit, appliedFilter]);

  useEffect(() => {
    setSending(sending);
  }, [sending]);

  useEffect(() => {
    setSendingDetails(sending);
  }, [sendingDetails]);

  useEffect(() => {
    setDetails(undefined);
    loadDetails();
  }, [
    selectedResourceUuid,
    selectedResourceDetails?.parentResourceId,
    selectedResourceDetails?.resourceId
  ]);

  return { initAutorefreshAndLoad };
};

export default useLoadResources;
