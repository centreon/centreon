import * as React from 'react';

import { useTranslation } from 'react-i18next';

import { SvgIcon, IconButton as MuiIconButton } from '@mui/material';
import DragIndicatorIcon from '@mui/icons-material/DragIndicator';

import { labelDragHandle } from '../../translatedLabels';

interface DraggableIconButtonProps {
  className?: string;
  columnLabel?: string;
}

const DraggableIconButton = ({
  columnLabel,
  className,
  ...props
}: DraggableIconButtonProps): JSX.Element => {
  const { t } = useTranslation();

  return (
    <MuiIconButton
      className={className}
      {...props}
      aria-label={`${columnLabel} ${t(labelDragHandle)}`}
    >
      <SvgIcon fontSize="small">
        <DragIndicatorIcon />
      </SvgIcon>
    </MuiIconButton>
  );
};

export { DraggableIconButton };
