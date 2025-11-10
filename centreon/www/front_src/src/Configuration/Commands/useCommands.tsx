import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import {
  commandDecoder,
  commandsEndpoint,
  commandsListDecoder,
  getCommandsEndpoint
} from './api';

import { APIType, FieldType, FilterConfiguration } from '../models';
import { Command, Payload } from './models';

import { labelName, labelStatus } from './translatedLabels';

interface UseCommandsState {
  api: APIType;
  filtersConfiguration: Array<FilterConfiguration>;
}

export const adaptFormToApiPayload = (formData: Command): Payload => {
  return {
    ...formData
  };
};

const useCommands = (): UseCommandsState => {
  const { t } = useTranslation();

  const api: APIType = useMemo(
    () => ({
      endpoints: {
        getAll: commandsEndpoint,
        getOne: getCommandsEndpoint,
        deleteOne: getCommandsEndpoint,
        create: commandsEndpoint,
        update: getCommandsEndpoint
      },
      decoders: {
        getAll: commandsListDecoder,
        getOne: commandDecoder
      },
      adapter: adaptFormToApiPayload
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
        name: t(labelStatus),
        fieldType: FieldType.Status
      }
    ],
    []
  );

  return {
    api,
    filtersConfiguration
  };
};

export default useCommands;
