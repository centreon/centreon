import {
  type Group,
  type InputProps,
  InputType,
  type SelectEntry
} from '@centreon/ui';

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

// The five sections the US defines. They also drive the pinned navigation the
// shared form renders on its own from four groups up. The later subtasks fill
// them; only Host configuration carries fields today.
const useFormInputs = (): FormInputsState => {
  const { t } = useTranslation();

  const groups: Array<Group> = [
    { name: t(labelHostConfiguration), order: 1 },
    { name: t(labelNotification), order: 2 },
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
        customQueryParameters: [],
        endpoint: monitoringServersEndpoint,
        getOptionLabel: (option) => (option as SelectEntry)?.name
      },
      dataTestId: 'host-form-poller',
      fieldName: 'poller',
      group: t(labelHostConfiguration),
      label: t(labelMonitoringServer),
      required: true,
      type: InputType.SingleConnectedAutocomplete
    }
  ];

  return { groups, inputs };
};

export default useFormInputs;
