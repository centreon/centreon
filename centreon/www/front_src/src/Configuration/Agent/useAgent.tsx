import { useAtomValue } from 'jotai';
import { equals } from 'ramda';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import {
  adaptCMAConfigurationToAPI,
  adaptTelegrafConfigurationToAPI,
  agentConfigurationDecoder,
  agentConfigurationsEndpoint,
  agentConfigurationsListingDecoder,
  getAgentConfigurationEndpoint,
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
}

const useAdditionnalConnectors = (): UseAdditionnalConnectorsState => {
  const { t } = useTranslation();

  const agentTypeForm = useAtomValue(agentTypeFormAtom);

  const api: APIType = useMemo(
    () => ({
      endpoints: {
        getAll: agentConfigurationsEndpoint,
        getOne: getAgentConfigurationEndpoint,
        deleteOne: getAgentConfigurationEndpoint,
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

  return {
    api,
    filtersConfiguration
  };
};

export default useAdditionnalConnectors;
