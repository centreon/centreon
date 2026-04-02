import type { SelectEntry } from '@centreon/ui';
import { getData, getUrlQueryParameters, useRequest } from '@centreon/ui';
import {
  isResourceStatusFullSearchEnabledAtom,
  refreshIntervalAtom
} from '@centreon/ui-context';

import { useAtom, useAtomValue, useSetAtom } from 'jotai';
import {
  always,
  equals,
  ifElse,
  isNil,
  map,
  mergeRight,
  not,
  pathEq,
  pathOr,
  prop
} from 'ramda';
import { useEffect, useRef } from 'react';
import { useTranslation } from 'react-i18next';

import { selectedVisualizationAtom } from '../../Actions/actionsAtoms';
import {
  resourcesEndpoint as allResourcesEndpoint,
  hostsEndpoint
} from '../../api/endpoint';
import {
  clearSelectedResourceDerivedAtom,
  detailsAtom,
  selectedResourceDetailsEndpointDerivedAtom,
  selectedResourcesDetailsAtom,
  selectedResourceUuidAtom,
  sendingDetailsAtom
} from '../../Details/detailsAtoms';
import type { ResourceDetails } from '../../Details/models';
import { resourceDetailsDecoder } from '../../decoders';
import {
  appliedFilterAtom,
  customFiltersAtom,
  getCriteriaValueDerivedAtom
} from '../../Filter/filterAtoms';
import { type ResourceListing, SortOrder, Visualization } from '../../models';
import {
  labelNoResourceFound,
  labelSomethingWentWrong
} from '../../translatedLabels';
import { listResources } from '../api';
import {
  currentCursorIndexAtom,
  cursorStackAtom,
  enabledAutorefreshAtom,
  limitAtom,
  listingAtom,
  sendingAtom
} from '../listingAtoms';
import useGetCriteriaName from './useGetCriteriaName';
import { getSearch } from './utils';

export interface LoadResources {
  initAutorefreshAndLoad: () => void;
}

const secondSortField = 'last_status_change';
const defaultSecondSortCriteria = { [secondSortField]: SortOrder.desc };

const useLoadResources = (): LoadResources => {
  const { t } = useTranslation();

  const { getCriteriaNames } = useGetCriteriaName();

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

  const [cursorStack, setCursorStack] = useAtom(cursorStackAtom);
  const [currentCursorIndex, setCurrentCursorIndex] = useAtom(
    currentCursorIndexAtom
  );
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
  const isResourceStatusFullSearchEnabled = useAtomValue(
    isResourceStatusFullSearchEnabledAtom
  );
  const setListing = useSetAtom(listingAtom);
  const setSending = useSetAtom(sendingAtom);
  const setSendingDetails = useSetAtom(sendingDetailsAtom);
  const clearSelectedResource = useSetAtom(clearSelectedResourceDerivedAtom);
  const refreshTimeoutRef = useRef<number>(undefined);
  const scheduleRef = useRef<() => void>(() => {});

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

  const load = (): Promise<void> => {
    const getCriteriaIds = (
      name: string
    ): Array<string | number> | undefined => {
      const criteriaValue = getCriteriaValue(name) as
        | Array<SelectEntry>
        | undefined;

      return criteriaValue?.map(prop('id'));
    };

    const getCriteriaLevels = (name: string): Array<number> => {
      const criteriaValue = getCriteriaValue(name) as
        | Array<SelectEntry>
        | undefined;

      const results = criteriaValue?.map(prop('name'));

      return results?.map((item) => Number(item)) as Array<number>;
    };

    if (getUrlQueryParameters().fromTopCounter) {
      return Promise.resolve();
    }

    const names = getCriteriaNames('names');
    const parentNames = getCriteriaNames('parent_names');

    const currentCursor = cursorStack[currentCursorIndex] ?? null;

    const listingPromise = sendRequest({
      cursor: currentCursor,
      endpoint: resourcesEndpoint,
      hostCategories: getCriteriaNames('host_categories'),
      hostGroups: getCriteriaNames('host_groups'),
      hostSeverities: getCriteriaNames('host_severities'),
      hostSeverityLevels: getCriteriaLevels('host_severity_levels'),
      limit,
      monitoringServers: getCriteriaNames('monitoring_servers'),
      resourceTypes: getCriteriaIds('resource_types'),
      search: mergeRight(
        getSearch({
          isResourceStatusFullSearchEnabled,
          searchCriteria: getCriteriaValue('search')
        }) || {},
        {
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
        }
      ),
      serviceCategories: getCriteriaNames('service_categories'),
      serviceGroups: getCriteriaNames('service_groups'),
      serviceSeverities: getCriteriaNames('service_severities'),
      serviceSeverityLevels: getCriteriaLevels('service_severity_levels'),
      sort: getSort(),
      states: getCriteriaIds('states'),
      statuses: getCriteriaIds('statuses'),
      statusTypes: getCriteriaIds('status_types')
    }).then((response) => {
      // Append next_cursor to the stack only when navigating forward for the first time.
      // Use functional update so the length check uses the current stack (not the stale
      // closure value that may predate a filter-change reset).
      const nextCursor = response.meta.next_cursor;
      if (nextCursor !== null) {
        setCursorStack((prev) => {
          if (prev.length <= currentCursorIndex + 1) {
            return [...prev, nextCursor];
          }

          return prev;
        });
      }

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

    if (!isNil(details)) {
      loadDetails();
    }

    return listingPromise;
  };

  const scheduleNextRefresh = (): void => {
    window.clearTimeout(refreshTimeoutRef.current);
    if (!enabledAutorefresh) return;
    refreshTimeoutRef.current = window.setTimeout(() => {
      load().finally(() => scheduleRef.current());
    }, refreshIntervalMs);
  };

  scheduleRef.current = scheduleNextRefresh;

  const initAutorefreshAndLoad = (): void => {
    if (isNil(customFilters)) {
      return;
    }

    window.clearTimeout(refreshTimeoutRef.current);
    load().finally(() => scheduleRef.current());
  };

  useEffect(() => {
    scheduleNextRefresh();
  }, [enabledAutorefresh, selectedResourceDetails?.resourceId]);

  useEffect(() => {
    return (): void => {
      window.clearTimeout(refreshTimeoutRef.current);
    };
  }, []);

  useEffect(() => {
    if (isNil(details)) {
      return;
    }

    scheduleNextRefresh();
  }, [isNil(details)]);

  useEffect(() => {
    initAutorefreshAndLoad();
  }, [currentCursorIndex, limit, appliedFilter]);

  useEffect(() => {
    // Reset cursor navigation when filters or limit change.
    setCursorStack([null]);
    setCurrentCursorIndex(0);
  }, [limit, appliedFilter]);

  useEffect(() => {
    setSending(sending);
  }, [sending]);

  useEffect(() => {
    setSendingDetails(sendingDetails);
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
