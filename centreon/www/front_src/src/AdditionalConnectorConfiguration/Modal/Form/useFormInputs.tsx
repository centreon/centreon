import { useTranslation } from 'react-i18next';

import { InputProps, InputType } from '@centreon/ui';

import { getPollersForConnectorTypeEndpoint } from '../../api/endpoints';
import {
  labelDescription,
  labelName,
  labelParameters,
  labelPollers,
  labelPort,
  labelSelectPollers,
  labelSelectType,
  labelType
} from '../../translatedLabels';
import ConnectorType from '../ConnectorType/ConnectorType';
import Parameters from '../Parameters/Parameters';
import Port from '../Parameters/Port';
import { useFormStyles } from '../useModalStyles';

interface FormInputsState {
  inputs: Array<InputProps>;
}

const useFormInputs = (): FormInputsState => {
  const { t } = useTranslation();
  const { classes } = useFormStyles();

  const inputs = [
    {
      dataTestId: labelName,
      fieldName: 'name',
      group: 'main',
      label: t(labelName),
      required: true,
      type: InputType.Text
    },
    {
      fieldName: 'description',
      group: 'main',
      label: t(labelDescription),
      text: {
        multilineRows: 3
      },
      type: InputType.Text
    },
    {
      additionalLabel: t(labelType),
      additionalLabelClassName: classes.additionalLabel,
      custom: {
        Component: ConnectorType
      },
      fieldName: 'type',
      group: 'main',
      label: t(labelSelectType),
      required: true,
      type: InputType.Custom
    },
    {
      additionalLabel: t(labelPollers),
      additionalLabelClassName: classes.additionalLabel,
      connectedAutocomplete: {
        additionalConditionParameters: [],
        chipColor: 'primary',
        endpoint: getPollersForConnectorTypeEndpoint({})
      },
      fieldName: 'pollers',
      group: 'main',
      label: t(labelSelectPollers),
      required: true,
      type: InputType.MultiConnectedAutocomplete
    },
    {
      additionalLabel: t(labelParameters),
      additionalLabelClassName: classes.parametersTitleText,
      custom: {
        Component: Parameters
      },
      fieldName: 'parameters.vcenters',
      group: 'main',
      label: t(labelParameters),
      type: InputType.Custom
    },
    {
      additionalLabel: t(labelPort),
      additionalLabelClassName: classes.additionalLabel,
      custom: {
        Component: Port
      },
      fieldName: 'prameters.port',
      group: 'main',
      label: t(labelPort),
      type: InputType.Custom
    }
  ];

  return { inputs };
};

export default useFormInputs;
