import { ComponentColumnProps } from '@centreon/ui';
import { platformFeaturesAtom, userAtom } from '@centreon/ui-context';
import { useAtomValue } from 'jotai';
import { equals, isNotNil } from 'ramda';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import {
  adaptCMAConfigurationToAPI,
  adaptTelegrafConfigurationToAPI,
  agentConfigurationDecoder,
  agentConfigurationsEndpoint,
  agentConfigurationsListingDecoder,
  getAgentConfigurationEndpoint,
  getPollerAgentEndpoint,
  getPollersEndpoint
} from './api';

import { APIType, FieldType, FilterConfiguration } from '../Common/models';
import { agentTypeFormAtom } from './atoms';
import { AgentType } from './models';
import { agentTypeOptions } from './utils';

import { labelName, labelPoller, labelType } from './translatedLabels';

interface UseAdditionnalConnectorsState {
  api: APIType;
  filtersConfiguration: Array<FilterConfiguration>;
  canDelete: (row: ComponentColumnProps) => boolean;
}

const useAdditionnalConnectors = (): UseAdditionnalConnectorsState => {
  const { t } = useTranslation();

  const agentTypeForm = useAtomValue(agentTypeFormAtom);
  const { isAdmin } = useAtomValue(userAtom);
  const { isCloudPlatform } = useAtomValue(platformFeaturesAtom);

  const api: APIType = useMemo(
    () => ({
      endpoints: {
        getAll: agentConfigurationsEndpoint,
        getOne: getAgentConfigurationEndpoint,
        deleteOne: getPollerAgentEndpoint,
        create: agentConfigurationsEndpoint,
        update: getAgentConfigurationEndpoint
      },
      decoders: {
        getAll: agentConfigurationsListingDecoder,
        getOne: agentConfigurationDecoder
      },
      adapter: equals(agentTypeForm, AgentType.Telegraf)
        ? adaptTelegrafConfigurationToAPI
        : adaptCMAConfigurationToAPI
    }),
    []
  );

  const filtersConfiguration: Array<FilterConfiguration> = useMemo(
    () => [
      {
        name: t(labelName),
        fieldName: 'name',
        fieldType: FieldType.Text
      },
      {
        name: t(labelType),
        fieldType: FieldType.MultiAutocomplete,
        options: agentTypeOptions,
        fieldName: 'type'
      },
      {
        name: t(labelPoller),
        fieldType: FieldType.MultiConnectedAutocomplete,
        getEndpoint: getPollersEndpoint,
        fieldName: 'poller.id'
      }
    ],
    []
  );

  const canDelete = (row): boolean => {
    const hasCentral = (
      isNotNil(row.internalListingParentId)
        ? row.internalListingParentRow?.pollers
        : row?.pollers
    )?.some((poller) => equals(poller?.isCentral, true));

    return isAdmin || !isCloudPlatform || !hasCentral;
  };

  return {
    api,
    filtersConfiguration,
    canDelete
  };
};

export default useAdditionnalConnectors;
