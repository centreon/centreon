import { Typography } from '@mui/material';

import { TableStyleAtom as TableStyle } from '../models';

interface Ellipsis {
  className?: string;
  dataStyle: TableStyle;
  disableRowCondition: boolean;
  formattedString: string;
  isRowHovered: boolean;
}
const EllipsisTypography = ({
  formattedString,
  isRowHovered,
  disableRowCondition,
  className,
  dataStyle
}: Ellipsis): JSX.Element => {
  return (
    <Typography
      className={`${className} text-[${dataStyle.body.fontSize}] truncate ${(!isRowHovered || disableRowCondition) && 'text-text-secondary'}`}
    >
      {formattedString}
    </Typography>
  );
};

export default EllipsisTypography;
