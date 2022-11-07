<<<<<<< HEAD
import { useEffect } from 'react';

import { isNil } from 'ramda';
import { useAtom } from 'jotai';
import { useAtomValue, useUpdateAtom } from 'jotai/utils';

import { getUrlQueryParameters, setUrlQueryParameters } from '@centreon/ui';

import {
  customTimePeriodAtom,
  selectedTimePeriodAtom,
} from '../Graph/Performance/TimePeriods/timePeriodAtoms';
import useTimePeriod from '../Graph/Performance/TimePeriods/useTimePeriod';

import { getTabIdFromLabel, getTabLabelFromId } from './tabs';
import { DetailsUrlQueryParameters } from './models';
import {
  defaultSelectedCustomTimePeriodAtom,
  defaultSelectedTimePeriodIdAtom,
  openDetailsTabIdAtom,
  selectedResourceIdAtom,
  selectedResourceParentIdAtom,
  selectedResourceParentTypeAtom,
  selectedResourceTypeAtom,
  selectedResourceUuidAtom,
  sendingDetailsAtom,
  tabParametersAtom,
} from './detailsAtoms';

const useDetails = (): void => {
  const [openDetailsTabId, setOpenDetailsTabId] = useAtom(openDetailsTabIdAtom);
  const [selectedResourceUuid, setSelectedResourceUuid] = useAtom(
    selectedResourceUuidAtom,
  );
  const [selectedResourceId, setSelectedResourceId] = useAtom(
    selectedResourceIdAtom,
  );
  const [selectedResourceParentId, setSelectedResourceParentId] = useAtom(
    selectedResourceParentIdAtom,
  );
  const [selectedResourceType, setSelectedResourceType] = useAtom(
    selectedResourceTypeAtom,
  );
  const [selectedResourceParentType, setSelectedResourceParentType] = useAtom(
    selectedResourceParentTypeAtom,
  );
  const [tabParameters, setTabParameters] = useAtom(tabParametersAtom);
  const customTimePeriod = useAtomValue(customTimePeriodAtom);
  const selectedTimePeriod = useAtomValue(selectedTimePeriodAtom);
  const sendingDetails = useAtomValue(sendingDetailsAtom);
  const setDefaultSelectedTimePeriodId = useUpdateAtom(
    defaultSelectedTimePeriodIdAtom,
  );
  const setDefaultSelectedCustomTimePeriod = useUpdateAtom(
    defaultSelectedCustomTimePeriodAtom,
  );

  useTimePeriod({
    sending: sendingDetails,
  });

  useEffect(() => {
=======
import * as React from 'react';

import { isNil, ifElse, pathEq, always, pathOr } from 'ramda';
import { useTranslation } from 'react-i18next';

import {
  getUrlQueryParameters,
  setUrlQueryParameters,
  useRequest,
  getData,
} from '@centreon/ui';

import { resourcesEndpoint } from '../api/endpoint';
import {
  labelNoResourceFound,
  labelSomethingWentWrong,
} from '../translatedLabels';
import { Resource } from '../models';
import useTimePeriod from '../Graph/Performance/TimePeriods/useTimePeriod';
import { AdjustTimePeriodProps } from '../Graph/Performance/models';

import { detailsTabId, getTabIdFromLabel, getTabLabelFromId } from './tabs';
import { TabId } from './tabs/models';
import {
  DetailsUrlQueryParameters,
  ResourceDetails,
  ServicesTabParameters,
  GraphTabParameters,
  TabParameters,
} from './models';
import { getStoredOrDefaultPanelWidth, storePanelWidth } from './storedDetails';
import {
  ChangeCustomTimePeriodProps,
  CustomTimePeriod,
  TimePeriod,
  TimePeriodId,
} from './tabs/Graph/models';

export interface DetailsState {
  adjustTimePeriod: (props: AdjustTimePeriodProps) => void;
  changeCustomTimePeriod: (props: ChangeCustomTimePeriodProps) => void;
  changeSelectedTimePeriod: (timePeriod: TimePeriodId) => void;
  clearSelectedResource: () => void;
  customTimePeriod: CustomTimePeriod;
  details?: ResourceDetails;
  getIntervalDates: () => [string, string];
  getSelectedResourceDetailsEndpoint: () => string | undefined;
  loadDetails: () => void;
  openDetailsTabId: TabId;
  panelWidth: number;
  periodQueryParameters: string;
  resourceDetailsUpdated: boolean;
  selectResource: (resource: Resource) => void;
  selectedResourceId?: number;
  selectedResourceParentId?: number;
  selectedResourceUuid?: string;
  selectedTimePeriod: TimePeriod | null;
  setGraphTabParameters: (parameters: GraphTabParameters) => void;
  setOpenDetailsTabId: React.Dispatch<React.SetStateAction<TabId>>;
  setPanelWidth: React.Dispatch<React.SetStateAction<number>>;
  setSelectedResourceId: React.Dispatch<
    React.SetStateAction<number | undefined>
  >;
  setSelectedResourceParentId: React.Dispatch<
    React.SetStateAction<number | undefined>
  >;
  setSelectedResourceParentType: React.Dispatch<
    React.SetStateAction<string | undefined>
  >;
  setSelectedResourceType: React.Dispatch<
    React.SetStateAction<string | undefined>
  >;
  setSelectedResourceUuid: React.Dispatch<
    React.SetStateAction<string | undefined>
  >;
  setServicesTabParameters: (parameters: ServicesTabParameters) => void;
  tabParameters: TabParameters;
}

const useDetails = (): DetailsState => {
  const { t } = useTranslation();
  const [openDetailsTabId, setOpenDetailsTabId] =
    React.useState<TabId>(detailsTabId);
  const [selectedResourceUuid, setSelectedResourceUuid] =
    React.useState<string>();
  const [selectedResourceId, setSelectedResourceId] = React.useState<number>();
  const [selectedResourceParentId, setSelectedResourceParentId] =
    React.useState<number>();
  const [selectedResourceType, setSelectedResourceType] =
    React.useState<string>();
  const [selectedResourceParentType, setSelectedResourceParentType] =
    React.useState<string>();
  const [details, setDetails] = React.useState<ResourceDetails>();
  const [tabParameters, setTabParameters] = React.useState<TabParameters>({});
  const [panelWidth, setPanelWidth] = React.useState(
    getStoredOrDefaultPanelWidth(550),
  );
  const [defaultSelectedTimePeriodId, setDefaultSelectedTimePeriodId] =
    React.useState<TimePeriodId | undefined>();
  const [defaultSelectedCustomTimePeriod, setDefaultSelectedCustomTimePeriod] =
    React.useState<CustomTimePeriod | undefined>();

  const { sendRequest, sending } = useRequest<ResourceDetails>({
    getErrorMessage: ifElse(
      pathEq(['response', 'status'], 404),
      always(t(labelNoResourceFound)),
      pathOr(t(labelSomethingWentWrong), ['response', 'data', 'message']),
    ),
    request: getData,
  });

  const selectResource = (resource: Resource): void => {
    setOpenDetailsTabId(detailsTabId);
    setSelectedResourceUuid(resource.uuid);
    setSelectedResourceId(resource.id);
    setSelectedResourceType(resource.type);
    setSelectedResourceParentType(resource?.parent?.type);
    setSelectedResourceParentId(resource?.parent?.id);
  };

  React.useEffect(() => {
>>>>>>> centreon/dev-21.10.x
    const urlQueryParameters = getUrlQueryParameters();

    const detailsUrlQueryParameters =
      urlQueryParameters.details as DetailsUrlQueryParameters;

    if (isNil(detailsUrlQueryParameters)) {
      return;
    }

    const {
      uuid,
      id,
      parentId,
      type,
      parentType,
      tab,
      tabParameters: tabParametersFromUrl,
      selectedTimePeriodId,
<<<<<<< HEAD
      customTimePeriod: customTimePeriodFromUrl,
=======
      customTimePeriod,
>>>>>>> centreon/dev-21.10.x
    } = detailsUrlQueryParameters;

    if (!isNil(tab)) {
      setOpenDetailsTabId(getTabIdFromLabel(tab));
    }

    setSelectedResourceUuid(uuid);
    setSelectedResourceId(id);
    setSelectedResourceParentId(parentId);
    setSelectedResourceType(type);
    setSelectedResourceParentType(parentType);
    setTabParameters(tabParametersFromUrl || {});
    setDefaultSelectedTimePeriodId(selectedTimePeriodId);
<<<<<<< HEAD
    setDefaultSelectedCustomTimePeriod(customTimePeriodFromUrl);
  }, []);

  useEffect(() => {
=======
    setDefaultSelectedCustomTimePeriod(customTimePeriod);
  }, []);

  const timePeriodProps = useTimePeriod({
    defaultSelectedCustomTimePeriod,
    defaultSelectedTimePeriodId,
    details,
    sending,
  });

  React.useEffect(() => {
>>>>>>> centreon/dev-21.10.x
    setUrlQueryParameters([
      {
        name: 'details',
        value: {
<<<<<<< HEAD
          customTimePeriod,
          id: selectedResourceId,
          parentId: selectedResourceParentId,
          parentType: selectedResourceParentType,
          selectedTimePeriodId: selectedTimePeriod?.id,
=======
          customTimePeriod: timePeriodProps.customTimePeriod,
          id: selectedResourceId,
          parentId: selectedResourceParentId,
          parentType: selectedResourceParentType,
          selectedTimePeriodId: timePeriodProps.selectedTimePeriod?.id,
>>>>>>> centreon/dev-21.10.x
          tab: getTabLabelFromId(openDetailsTabId),
          tabParameters,
          type: selectedResourceType,
          uuid: selectedResourceUuid,
        },
      },
    ]);
  }, [
    openDetailsTabId,
    selectedResourceId,
    selectedResourceType,
    selectedResourceParentType,
    selectedResourceParentType,
    tabParameters,
<<<<<<< HEAD
    selectedTimePeriod,
    customTimePeriod,
  ]);
=======
    timePeriodProps.selectedTimePeriod,
    timePeriodProps.customTimePeriod,
  ]);

  const getSelectedResourceDetailsEndpoint = (): string | undefined => {
    if (!isNil(selectedResourceParentId)) {
      return `${resourcesEndpoint}/${selectedResourceParentType}s/${selectedResourceParentId}/${selectedResourceType}s/${selectedResourceId}`;
    }

    return `${resourcesEndpoint}/${selectedResourceType}s/${selectedResourceId}`;
  };

  const clearSelectedResource = (): void => {
    setSelectedResourceUuid(undefined);
    setSelectedResourceId(undefined);
    setSelectedResourceParentId(undefined);
    setSelectedResourceParentType(undefined);
    setSelectedResourceType(undefined);
  };

  const loadDetails = (): void => {
    if (isNil(selectedResourceId)) {
      return;
    }

    sendRequest(getSelectedResourceDetailsEndpoint())
      .then(setDetails)
      .catch(() => {
        clearSelectedResource();
      });
  };

  React.useEffect(() => {
    setDetails(undefined);
    loadDetails();
  }, [selectedResourceUuid]);

  React.useEffect(() => {
    storePanelWidth(panelWidth);
  }, [panelWidth]);

  const setServicesTabParameters = (
    parameters: ServicesTabParameters,
  ): void => {
    setTabParameters({ ...tabParameters, services: parameters });
  };

  const setGraphTabParameters = (parameters: GraphTabParameters): void => {
    setTabParameters({ ...tabParameters, graph: parameters });
  };

  return {
    clearSelectedResource,
    details,
    getSelectedResourceDetailsEndpoint,
    loadDetails,
    openDetailsTabId,
    panelWidth,
    selectResource,
    selectedResourceId,
    selectedResourceParentId,
    selectedResourceUuid,
    setGraphTabParameters,
    setOpenDetailsTabId,
    setPanelWidth,
    setSelectedResourceId,
    setSelectedResourceParentId,
    setSelectedResourceParentType,
    setSelectedResourceType,
    setSelectedResourceUuid,
    setServicesTabParameters,
    tabParameters,
    ...timePeriodProps,
  };
>>>>>>> centreon/dev-21.10.x
};

export default useDetails;
