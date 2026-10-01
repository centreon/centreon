import {
  type Group,
  type InputProps,
  InputType,
  type SelectEntry
} from '@centreon/ui';

import { useTranslation } from 'react-i18next';

import { namedEntitiesListDecoder } from '../api/decoders';
import {
  hostGroupsEndpoint,
  hostsBaseEndpoint,
  pollersEndpoint
} from '../api/endpoints';
import {
  labelDataProcessing,
  labelHostConfiguration,
  labelHostExtendedInfos,
  labelHostGroups,
  labelIpAddress,
  labelMonitoringServer,
  labelName,
  labelNotification,
  labelRelations
} from '../translatedLabels';

interface FormInputsState {
  inputs: Array<InputProps>;
  groups: Array<Group>;
}

interface Props {
  canEdit: boolean;
  isCloudPlatform: boolean;
}

// The five sections of the US. They also drive the pinned navigation, which
// the shared form renders on its own from four groups up.
const useFormInputs = ({
  canEdit,
  isCloudPlatform
}: Props): FormInputsState => {
  const { t } = useTranslation();

  const groups: Array<Group> = [
    { name: t(labelHostConfiguration), order: 1 },
    // Notifications are an onPrem concern; the US has no such tab on cloud.
    ...(isCloudPlatform ? [] : [{ name: t(labelNotification), order: 2 }]),
    { name: t(labelRelations), order: 3 },
    { name: t(labelDataProcessing), order: 4 },
    { name: t(labelHostExtendedInfos), order: 5 }
  ];

  const inputs: Array<InputProps> = [
    {
      dataTestId: 'host-form-name',
      fieldName: 'name',
      group: t(labelHostConfiguration),
      label: t(labelName),
      required: true,
      type: InputType.Text
    },
    {
      dataTestId: 'host-form-address',
      fieldName: 'address',
      group: t(labelHostConfiguration),
      label: t(labelIpAddress),
      required: true,
      type: InputType.Text
    },
    {
      connectedAutocomplete: {
        additionalConditionParameters: [],
        // The list the listing filter reads, so a poller that can be filtered
        // on is a poller that can be selected.
        baseEndpoint: hostsBaseEndpoint,
        customQueryParameters: [],
        decoder: namedEntitiesListDecoder,
        endpoint: pollersEndpoint,
        getOptionLabel: (option) => (option as SelectEntry)?.name,
        useNewAPIFormat: true
      },
      dataTestId: 'host-form-poller',
      fieldName: 'poller',
      group: t(labelHostConfiguration),
      label: t(labelMonitoringServer),
      required: true,
      type: InputType.SingleConnectedAutocomplete
    },
    {
      connectedAutocomplete: {
        additionalConditionParameters: [],
        baseEndpoint: hostsBaseEndpoint,
        chipColor: 'primary',
        customQueryParameters: [],
        decoder: namedEntitiesListDecoder,
        endpoint: hostGroupsEndpoint,
        useNewAPIFormat: true
      },
      dataTestId: 'host-form-groups',
      fieldName: 'groups',
      // `CreateHostInput` counts at least one group on a cloud platform and
      // leaves it optional elsewhere, so the field follows the platform.
      getRequired: () => isCloudPlatform,
      group: t(labelRelations),
      label: t(labelHostGroups),
      type: InputType.MultiConnectedAutocomplete
    }
  ];

  return {
    groups,
    // Frozen here rather than per input: the sections to come add dozens of
    // fields, and one forgotten `getDisabled` is an editable field on a form
    // its user may only read.
    inputs: inputs.map((input) => ({ ...input, getDisabled: () => !canEdit }))
  };
};

export default useFormInputs;
