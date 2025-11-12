import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import {
  commandDecoder,
  commandsEndpoint,
  commandsListDecoder,
  getCommandEndpoint
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
    name: formData.name,
    type: formData.type,
    command_line: formData.commandLine,
    is_shell_enabled: formData.isShellEnabled,
    connector: formData.connector?.id || null,
    comment: formData.comment || null
  };
};

const useCommands = (): UseCommandsState => {
  const { t } = useTranslation();

  const api: APIType = useMemo(
    () => ({
      endpoints: {
        getAll: commandsEndpoint,
        getOne: getCommandEndpoint,
        deleteOne: getCommandEndpoint,
        create: commandsEndpoint,
        update: getCommandEndpoint
      },
      decoders: {
        getAll: commandsListDecoder,
        getOne: commandDecoder
      },
      adapter: adaptFormToApiPayload,
      apiFormat: 'JSON-LD'
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
