import { Group, InputProps, InputType } from '@centreon/ui';

import { useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';

import {
  listTokensEndpoint,
  tokensSearchConditions
} from '../../../../../AgentConfiguration/api/endpoints';
import { listTokensDecoder } from '../../../../../AuthenticationTokens/api';
import {
  labelEnterPollerName,
  labelGenerateAndCopyCommand,
  labelPollerName,
  labelSelectPollerEnvironment,
  labelSelectPollerToken,
  labelSelectToken
} from '../../../translatedLabels';
import { isGeneratedAtom } from './atoms';
import CommandSection from './CommandSection';
import EnvironmentSelector from './EnvironmentSelector';

export const useInputs = (): {
  groups: Array<Group>;
  inputs: Array<InputProps>;
} => {
  const { t } = useTranslation();
  const isGenerated = useAtomValue(isGeneratedAtom);

  return {
    groups: [
      {
        isDividerHidden: true,
        name: t(labelEnterPollerName),
        order: 1
      },
      {
        isDividerHidden: true,
        name: t(labelSelectPollerEnvironment),
        order: 2
      },
      {
        isDividerHidden: true,
        name: t(labelSelectPollerToken),
        order: 3
      },
      {
        isDividerHidden: true,
        name: t(labelGenerateAndCopyCommand),
        order: 4
      }
    ],
    inputs: [
      {
        fieldName: 'pollerName',
        getDisabled: () => isGenerated,
        group: t(labelEnterPollerName),
        label: t(labelPollerName),
        required: true,
        type: InputType.Text
      },
      {
        custom: {
          Component: EnvironmentSelector
        },
        fieldName: 'environment',
        getDisabled: () => isGenerated,
        group: t(labelSelectPollerEnvironment),
        label: '',
        type: InputType.Custom
      },
      {
        connectedAutocomplete: {
          additionalConditionParameters: tokensSearchConditions,
          customQueryParameters: [],
          decoder: listTokensDecoder,
          endpoint: listTokensEndpoint,
          filterKey: 'token_name'
        },
        fieldName: 'token',
        getDisabled: () => isGenerated,
        group: t(labelSelectPollerToken),
        label: labelSelectToken,
        required: true,
        type: InputType.SingleConnectedAutocomplete
      },
      {
        custom: {
          Component: CommandSection
        },
        fieldName: '',
        group: t(labelGenerateAndCopyCommand),
        hideInput: () => !isGenerated,
        label: '',
        type: InputType.Custom
      }
    ]
  };
};
