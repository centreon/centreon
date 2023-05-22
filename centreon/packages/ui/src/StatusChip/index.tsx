import { ReactNode } from 'react';

import { equals } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import { Chip, ChipProps } from '@mui/material';

import { getStatusColors, SeverityCode } from '../utils/statuses';

export type Props = {
  clickable?: boolean;
  label?: string | ReactNode;
  severityCode: SeverityCode;
} & ChipProps;

interface StylesProps {
  severityCode: SeverityCode;
}

const useStyles = makeStyles<StylesProps>()((theme, { severityCode }) => ({
  chip: {
    '&:hover': { ...getStatusColors({ severityCode, theme }) },
    ...getStatusColors({ severityCode, theme }),
    '& .MuiChip-label': {
      alignItems: 'center',
      display: 'flex',
      height: '100%'
    }
  }
}));

const StatusChip = ({
  severityCode,
  label,
  clickable = false,
  className,
  ...rest
}: Props): JSX.Element => {
  const { classes, cx } = useStyles({
    severityCode
  });

  const lowerLabel = (name: string): string => {
    return name.charAt(0).toUpperCase() + name.slice(1).toLowerCase();
  };

  return (
    <Chip
      className={cx(classes.chip, className)}
      clickable={clickable}
      label={
        equals(typeof label, 'string') ? lowerLabel(label as string) : label
      }
      {...rest}
    />
  );
};

export { SeverityCode, getStatusColors };
export default StatusChip;
