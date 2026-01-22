import { Method } from '@centreon/ui';

import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import { APIType, FieldType, FilterConfiguration } from '../models';
import {
  commandDecoder,
  commandsEndpoint,
  commandsListDecoder,
  duplicateCommandsEndpoint,
  getCommandEndpoint
} from './api';
import { Command, Payload } from './models';
import {
  labelCheck,
  labelDiscovery,
  labelLockedElements,
  labelMiscellaneous,
  labelName,
  labelNotification,
  labelStatus,
  labelType
} from './translatedLabels';
import { useUserPermissions } from './useUserPermissions';

interface UseCommandsState {
  api: APIType;
  filtersConfiguration: Array<FilterConfiguration>;
}

export const adaptFormToApiPayload = (formData: Command): Payload => ({
  command_line: formData.commandLine,
  comment: formData.comment || null,
  connector: formData.connector?.id || null,
  is_shell_enabled: formData.isShellEnabled,
  name: formData.name,
  type: formData.type
});

const useCommands = (): UseCommandsState => {
  const { t } = useTranslation();

  const {
    canViewCheckCommands,
    canViewNotificationCommands,
    canViewDiscoveryCommands,
    canViewMiscellaneousCommands
  } = useUserPermissions();

  const typeOptions = useMemo(
    () => [
      {
        disabled: !canViewNotificationCommands,
        id: 'Notification',
        name: labelNotification
      },
      { disabled: !canViewCheckCommands, id: 'Check', name: labelCheck },
      {
        disabled: !canViewMiscellaneousCommands,
        id: 'Miscellaneous',
        name: labelMiscellaneous
      },
      {
        disabled: !canViewDiscoveryCommands,
        id: 'Discovery',
        name: labelDiscovery
      }
    ],
    [
      canViewCheckCommands,
      canViewNotificationCommands,
      canViewDiscoveryCommands,
      canViewMiscellaneousCommands
    ]
  );

  const api: APIType = useMemo(
    () => ({
      adapter: adaptFormToApiPayload,
      apiFormat: 'JSON-LD',
      decoders: {
        getAll: commandsListDecoder,
        getOne: commandDecoder
      },
      endpoints: {
        create: commandsEndpoint,
        deleteOne: getCommandEndpoint,
        disable: getCommandEndpoint,
        duplicate: duplicateCommandsEndpoint,
        enable: getCommandEndpoint,
        getAll: commandsEndpoint,
        getOne: getCommandEndpoint,
        update: getCommandEndpoint
      },
      isSingleDuplicate: true,
      methods: {
        disable: Method.PATCH,
        enable: Method.PATCH,
        update: Method.PATCH
      }
    }),
    []
  );

  const filtersConfiguration: Array<FilterConfiguration> = useMemo(
    () => [
      {
        fieldName: 'name',
        fieldType: FieldType.Text,
        name: t(labelName)
      },
      {
        fieldName: 'type',
        fieldType: FieldType.Checkboxes,
        name: t(labelType),
        options: typeOptions
      },
      {
        fieldType: FieldType.Status,
        name: t(labelStatus)
      },
      {
        fieldName: 'is_from_monitoring_connector',
        fieldType: FieldType.Checkbox,
        name: t(labelLockedElements)
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
