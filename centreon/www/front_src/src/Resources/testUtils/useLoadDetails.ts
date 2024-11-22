import { useEffect } from 'react';

import { useAtom, useAtomValue, useSetAtom } from 'jotai';
import { always, ifElse, isNil, pathEq, pathOr } from 'ramda';
import { useTranslation } from 'react-i18next';

import { getData, useRequest } from '@centreon/ui';

import {
  clearSelectedResourceDerivedAtom,
  detailsAtom,
  selectedResourceDetailsEndpointDerivedAtom,
  selectedResourceUuidAtom,
  selectedResourcesDetailsAtom,
  sendingDetailsAtom
} from '../Details/detailsAtoms';
import { ResourceDetails } from '../Details/models';
import { ChangeCustomTimePeriodProps } from '../Details/tabs/Graph/models';
import {
  customTimePeriodAtom,
  getNewCustomTimePeriod,
  resourceDetailsUpdatedAtom,
  selectedTimePeriodAtom
} from '../Graph/Performance/TimePeriods/timePeriodAtoms';
import useTimePeriod from '../Graph/Performance/TimePeriods/useTimePeriod';
import { resourceDetailsDecoder } from '../decoders';
import {
  labelNoResourceFound,
  labelSomethingWentWrong
} from '../translatedLabels';

export interface DetailsState {
  changeCustomTimePeriod: (props: ChangeCustomTimePeriodProps) => void;
  loadDetails: () => void;
}

const useLoadDetails = (): DetailsState => {
  const { t } = useTranslation();

  const { sendRequest } = useRequest<ResourceDetails>({
    decoder: resourceDetailsDecoder,
    getErrorMessage: ifElse(
      pathEq(404, ['response', 'status']),
      always(t(labelNoResourceFound)),
      pathOr(t(labelSomethingWentWrong), ['response', 'data', 'message'])
    ),
    request: getData
  });

  const [customTimePeriod, setCustomTimePeriod] = useAtom(customTimePeriodAtom);
  const selectedResource = useAtomValue(selectedResourcesDetailsAtom);
  const selectedResourceUuid = useAtomValue(selectedResourceUuidAtom);
  const selectedResourceDetailsEndpoint = useAtomValue(
    selectedResourceDetailsEndpointDerivedAtom
  );
  const sendingDetails = useAtomValue(sendingDetailsAtom);
  const setDetails = useSetAtom(detailsAtom);
  const clearSelectedResource = useSetAtom(clearSelectedResourceDerivedAtom);
  const setSelectedTimePeriod = useSetAtom(selectedTimePeriodAtom);
  const setResourceDetailsUpdated = useSetAtom(resourceDetailsUpdatedAtom);

  useTimePeriod({
    sending: sendingDetails
  });

  const loadDetails = (): void => {
    if (isNil(selectedResource?.resourceId)) {
      return;
    }

    sendRequest({
      endpoint: selectedResourceDetailsEndpoint
    })
      .then(setDetails)
      .catch(() => {
        clearSelectedResource();
      });
  };

  const changeCustomTimePeriod = ({ date, property }): void => {
    const newCustomTimePeriod = getNewCustomTimePeriod({
      ...customTimePeriod,
      [property]: date
    });

    setCustomTimePeriod(newCustomTimePeriod);
    setSelectedTimePeriod(null);
    setResourceDetailsUpdated(false);
  };

  useEffect(() => {
    setDetails(undefined);
    loadDetails();
  }, [
    selectedResourceUuid,
    selectedResource?.parentResourceId,
    selectedResource?.resourceId
  ]);

  return {
    changeCustomTimePeriod,
    loadDetails
  };
};

export default useLoadDetails;
