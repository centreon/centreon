import { ReactElement } from 'react';

import { ActionVariants } from '../../../ActionsList/models';

interface Labels {
  cancel: string;
  confirm: {
    label: string;
    secondaryLabel?: string;
  };
}

export interface Props {
  children: (toggleTooltip) => ReactElement;
  confirmVariant?: ActionVariants;
  labels: Labels;
  onConfirm: () => void;
}
