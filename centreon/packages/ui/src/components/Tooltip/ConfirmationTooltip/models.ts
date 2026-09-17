import type React from 'react';
import type { ReactElement } from 'react';

import type { ActionVariants } from '../../../ActionsList/models';

interface Labels {
  cancel: string;
  confirm: {
    label: string;
    secondaryLabel?: string;
  };
}

export interface Props {
  children: (params: {
    isOpen: boolean;
    toggleTooltip: (event: React.MouseEvent<HTMLButtonElement>) => void;
  }) => ReactElement;
  confirmVariant?: ActionVariants;
  labels: Labels;
  onConfirm: () => void;
}
