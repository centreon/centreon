import { InputProps, InputType } from '@centreon/ui';

import {
  CommandSection,
  EnvironmentSelector,
  PollerNameSection,
  TokenSection
} from './Components';

export const useInputs = (): Array<InputProps> => {
  return [
    {
      custom: {
        Component: PollerNameSection
      },
      fieldName: 'pollerName',
      group: '',
      label: 'pollerName',
      type: InputType.Custom
    },
    {
      custom: {
        Component: EnvironmentSelector
      },
      fieldName: 'environment',
      group: '',
      label: 'environment',
      type: InputType.Custom
    },
    {
      custom: {
        Component: TokenSection
      },
      fieldName: 'token',
      group: '',
      label: 'token',
      type: InputType.Custom
    },
    {
      custom: {
        Component: CommandSection
      },
      fieldName: '',
      group: '',
      label: 'command',
      type: InputType.Custom
    }
  ];
};
