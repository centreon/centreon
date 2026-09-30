import {
  type Group,
  type InputProps,
  InputType,
  type SelectEntry
} from '@centreon/ui';
import { platformFeaturesAtom } from '@centreon/ui-context';

import { useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';

import { monitoringServersEndpoint } from '../api/endpoints';
import {
  labelDataProcessing,
  labelHostConfiguration,
  labelHostExtendedInfos,
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

// The five sections of the US. They also drive the pinned navigation, which
// the shared form renders on its own from four groups up.
const useFormInputs = ({ canEdit }: { canEdit: boolean }): FormInputsState => {
  const { t } = useTranslation();

  const platformFeatures = useAtomValue(platformFeaturesAtom);
  const isCloudPlatform = platformFeatures?.isCloudPlatform;

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
      getDisabled: () => !canEdit,
      group: t(labelHostConfiguration),
      label: t(labelName),
      required: true,
      type: InputType.Text
    },
    {
      dataTestId: 'host-form-address',
      fieldName: 'address',
      getDisabled: () => !canEdit,
      group: t(labelHostConfiguration),
      label: t(labelIpAddress),
      required: true,
      type: InputType.Text
    },
    {
      connectedAutocomplete: {
        additionalConditionParameters: [],
        customQueryParameters: [],
        endpoint: monitoringServersEndpoint,
        getOptionLabel: (option) => (option as SelectEntry)?.name
      },
      dataTestId: 'host-form-poller',
      fieldName: 'poller',
      getDisabled: () => !canEdit,
      group: t(labelHostConfiguration),
      label: t(labelMonitoringServer),
      required: true,
      type: InputType.SingleConnectedAutocomplete
    }
  ];

  return { groups, inputs };
};

export default useFormInputs;
