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
import type { ResourceDetails } from '../../Details/models';
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
import { resourceDetailsDecoder } from '../../decoders';
import { type ResourceListing, SortOrder, Visualization } from '../../models';
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

import type { Search } from './models';

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
      endpoint: selectedResourceDetailsEndpoint
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
        setListing(response);

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
