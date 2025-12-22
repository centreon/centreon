import { Method } from '@centreon/ui';

import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import { useUserPermissions } from './useUserPermissions';

import {
  commandDecoder,
  commandsEndpoint,
  commandsListDecoder,
  duplicateCommandsEndpoint,
  getCommandEndpoint
} from './api';

import { APIType, FieldType, FilterConfiguration } from '../models';
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

interface UseCommandsState {
  api: APIType;
  filtersConfiguration: Array<FilterConfiguration>;
}

export const adaptFormToApiPayload = (formData: Command): Payload => ({
  name: formData.name,
  type: formData.type,
  command_line: formData.commandLine,
  is_shell_enabled: formData.isShellEnabled,
  connector: formData.connector?.id || null,
  comment: formData.comment || null
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
        id: 'Notification',
        name: labelNotification,
        disabled: !canViewNotificationCommands
      },
      { id: 'Check', name: labelCheck, disabled: !canViewCheckCommands },
      {
        id: 'Miscellaneous',
        name: labelMiscellaneous,
        disabled: !canViewMiscellaneousCommands
      },
      {
        id: 'Discovery',
        name: labelDiscovery,
        disabled: !canViewDiscoveryCommands
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
      endpoints: {
        getAll: commandsEndpoint,
        getOne: getCommandEndpoint,
        deleteOne: getCommandEndpoint,
        create: commandsEndpoint,
        update: getCommandEndpoint,
        enable: getCommandEndpoint,
        disable: getCommandEndpoint,
        duplicate: duplicateCommandsEndpoint
      },
      decoders: {
        getAll: commandsListDecoder,
        getOne: commandDecoder
      },
      methods: {
        update: Method.PATCH,
        enable: Method.PATCH,
        disable: Method.PATCH
      },
      adapter: adaptFormToApiPayload,
      apiFormat: 'JSON-LD',
      isSingleDuplicate: true
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
        fieldName: 'type',
        fieldType: FieldType.Checkboxes,
        options: typeOptions
      },
      {
        name: t(labelStatus),
        fieldType: FieldType.Status
      },
      {
        name: t(labelLockedElements),
        fieldName: 'is_from_monitoring_connector',
        fieldType: FieldType.Checkbox
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
