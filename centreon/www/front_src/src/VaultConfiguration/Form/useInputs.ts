import { InputProps, InputType } from '@centreon/ui';
import { isNil } from 'ramda';
import { useTranslation } from 'react-i18next';
import {
  labelPort,
  labelRoleID,
  labelRootPath,
  labelSecretID,
  labelVaultAddress
} from '../translatedLabels';
import { portRegex } from './useValidationSchema';

export const useInputs = (): Array<InputProps> => {
  const { t } = useTranslation();

  return [
    {
      type: InputType.Text,
      label: t(labelVaultAddress),
      required: true,
      fieldName: 'address',
      group: '',
      change: ({ setFieldValue, value, setFieldTouched }) => {
        const port = value.match(portRegex);
        const url = value.match(/https?:\/\//);

        if (isNil(port)) {
          const addressWithoutProtocol = url
            ? value.replace(url[0], '')
            : value;
          setFieldValue('address', addressWithoutProtocol);
          return;
        }
        const newAddress = value.replace(port[0], '');
        const addressWithoutProtocol = url
          ? newAddress.replace(url[0], '')
          : newAddress;

        setFieldTouched('port', true);
        setFieldValue('address', addressWithoutProtocol);
        setFieldValue('port', port[0].substring(1));
      }
    },
    {
      type: InputType.Text,
      label: t(labelPort),
      required: true,
      fieldName: 'port',
      text: {
        type: 'number',
        min: 1
      },

      group: ''
    },
    {
      type: InputType.Text,
      label: t(labelRootPath),
      required: true,
      fieldName: 'rootPath',
      group: ''
    },
    {
      type: InputType.Password,
      label: t(labelRoleID),
      required: true,
      fieldName: 'roleId',
      group: ''
    },
    {
      type: InputType.Password,
      label: t(labelSecretID),
      required: true,
      fieldName: 'secretId',
      group: ''
    }
  ];
};
