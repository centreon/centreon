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
      },
      fieldName: 'address',
      group: '',
      label: t(labelVaultAddress),
      required: true,
      type: InputType.Text
    },
    {
      fieldName: 'port',

      group: '',
      label: t(labelPort),
      required: true,
      text: {
        min: 1,
        type: 'number'
      },
      type: InputType.Text
    },
    {
      fieldName: 'rootPath',
      group: '',
      label: t(labelRootPath),
      required: true,
      type: InputType.Text
    },
    {
      fieldName: 'roleId',
      group: '',
      label: t(labelRoleID),
      required: true,
      type: InputType.Password
    },
    {
      fieldName: 'secretId',
      group: '',
      label: t(labelSecretID),
      required: true,
      type: InputType.Password
    }
  ];
};
