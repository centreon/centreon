import { useTranslation } from 'react-i18next';

import { InputProps, InputType } from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';
import { useAtomValue } from 'jotai';
import { equals } from 'ramda';
import { listUsers } from '../../api/endpoints';
import { tokenAtom } from '../../atoms';
import { TokenType } from '../../models';
import {
  labelDuration,
  labelName,
  labelToken,
  labelType,
  labelUser
} from '../../translatedLabels';
import { tokenTypes } from '../utils';
import DurationField from './DurationField/DurationField';
import TokenField from './TokenField/TokenField';
import TokenCopyWarning from './Warning/Warning';

interface FormInputsState {
  inputs: Array<InputProps>;
}

const useFormInputs = (): FormInputsState => {
  const { t } = useTranslation();
  const { isAdmin, canManageApiTokens } = useAtomValue(userAtom);
  const token = useAtomValue(tokenAtom);

  const userSearchConditions = isAdmin
    ? {}
    : {
        field: 'is_admin',
        values: {
          $eq: '0'
        }
      };

  const inputs = [
    {
      dataTestId: labelName,
      fieldName: 'name',
      label: t(labelName),
      required: true,
      type: InputType.Text,
      getDisabled: () => token
    },
    {
      fieldName: 'type',
      label: t(labelType),
      required: true,
      type: InputType.SingleAutocomplete,
      autocomplete: {
        options: tokenTypes
      },
      getDisabled: () => token
    },
    {
      custom: {
        Component: DurationField
      },
      fieldName: 'duration',
      label: t(labelDuration),
      required: true,
      type: InputType.Custom
    },
    {
      connectedAutocomplete: {
        additionalConditionParameters: [userSearchConditions],
        endpoint: listUsers,
        filterKey: 'name'
      },
      fieldName: 'user',
      hideInput: (values) => equals(values?.type?.id, TokenType.CMA),
      label: t(labelUser),
      required: true,
      getDisabled: () => !canManageApiTokens || token,
      type: InputType.SingleConnectedAutocomplete
    },
    {
      custom: {
        Component: TokenField
      },
      fieldName: 'token',
      label: t(labelToken),
      hideInput: () => !token,
      type: InputType.Custom
    },
    {
      custom: {
        Component: TokenCopyWarning
      },
      fieldName: 'warning',
      label: t(labelToken),
      hideInput: (values) => !token || equals(values?.type?.id, TokenType.CMA),
      type: InputType.Custom
    }
  ];

  return { inputs };
};

export default useFormInputs;
