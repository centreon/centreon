import * as React from 'react';

import { useTranslation } from 'react-i18next';

import SvgIcon from '@mui/material/SvgIcon';

import { labelDragHandle } from '../../translatedLabels';

interface Icon {
  className?: string;
  columnLabel?: string;
  isInteractive?: boolean;
  visible?: boolean;
}

const DraggableIcon = ({
  visible = false,
  columnLabel,
  className,
  isInteractive = false,
  ...rest
}: Icon): JSX.Element => {
  const { t } = useTranslation();

  return (
    <SvgIcon
      aria-label={`${columnLabel} ${t(labelDragHandle)}`}
      fontSize="medium"
      {...rest}
      className={className}
      role={isInteractive ? 'button' : undefined}
      tabIndex={isInteractive ? 0 : undefined}
    >
      {visible && (
        <path d="M 12 8 c 1.1 0 2 -0.9 2 -2 s -0.9 -2 -2 -2 s -2 0.9 -2 2 s 0.9 2 2 2 Z m 0 2 c -1.1 0 -2 0.9 -2 2 s 0.9 2 2 2 s 2 -0.9 2 -2 s -0.9 -2 -2 -2 Z m 0 6 c -1.1 0 -2 0.9 -2 2 s 0.9 2 2 2 s 2 -0.9 2 -2 s -0.9 -2 -2 -2 Z" />
      )}
    </SvgIcon>
  );
};

export default DraggableIcon;
