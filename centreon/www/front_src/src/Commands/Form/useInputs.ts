import { Group, InputProps, InputType } from '@centreon/ui';

import { useTranslation } from 'react-i18next';
import { useInputsStyles } from './Modal.styles';

import {
  labelAdditionalInformation,
  labelComments,
  labelGeneralInformation,
  labelName
} from '../translatedLabels';

export const useInputs = (): {
  groups: Array<Group>;
  inputs: Array<InputProps>;
} => {
  const { classes } = useInputsStyles();
  const { t } = useTranslation();

  const titleAttributes = {
    classes: { root: classes.titleGroup },
    variant: 'subtitle1'
  };

  return {
    groups: [
      {
        name: t(labelGeneralInformation),
        order: 1,
        titleAttributes,
        isDividerHidden: true
      },
      {
        name: t(labelAdditionalInformation),
        order: 2,
        titleAttributes,
        isDividerHidden: true
      }
    ],
    inputs: [
      {
        type: InputType.Text,
        fieldName: 'name',
        required: true,
        label: t(labelName),
        group: t(labelGeneralInformation)
      },
      {
        type: InputType.Text,
        fieldName: 'comments',
        required: true,
        label: t(labelComments),
        group: t(labelAdditionalInformation),
        text: {
          multilineRows: 3
        }
      }
    ]
  };
};
