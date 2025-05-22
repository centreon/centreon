import { Typography } from '@mui/material';

import { TableStyleAtom as TableStyle } from '../models';

interface Ellipsis {
  className?: string;
  dataStyle: TableStyle;
  disableRowCondition: boolean;
  formattedString: string;
  isRowHovered: boolean;
  isRowHighlighted?: boolean;
}
const EllipsisTypography = ({
  formattedString,
  isRowHovered,
  disableRowCondition,
  className,
  dataStyle,
  isRowHighlighted
}: Ellipsis): JSX.Element => (
  <Typography
    className={`${className} text-[${dataStyle.body.fontSize}] truncate ${!isRowHighlighted && (!isRowHovered || disableRowCondition) && 'text-text-secondary'}`}
  >
    {formattedString}
  </Typography>
);

export default EllipsisTypography;
