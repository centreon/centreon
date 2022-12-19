import * as React from 'react';

import SvgIcon from '@mui/material/SvgIcon';

interface Icon {
  className?: string;
  columnLabel?: string;
  visible?: boolean;
}

const InvisibleIcon = ({
  visible = false,
  columnLabel,
  className,
  ...rest
}: Icon): JSX.Element => {
  return (
    <SvgIcon
      aria-label={columnLabel}
      fontSize="small"
      {...rest}
      className={className}
    >
      {visible && (
        <path d="M 12 8 c 1.1 0 2 -0.9 2 -2 s -0.9 -2 -2 -2 s -2 0.9 -2 2 s 0.9 2 2 2 Z m 0 2 c -1.1 0 -2 0.9 -2 2 s 0.9 2 2 2 s 2 -0.9 2 -2 s -0.9 -2 -2 -2 Z m 0 6 c -1.1 0 -2 0.9 -2 2 s 0.9 2 2 2 s 2 -0.9 2 -2 s -0.9 -2 -2 -2 Z" />
      )}
    </SvgIcon>
  );
};

export default InvisibleIcon;
