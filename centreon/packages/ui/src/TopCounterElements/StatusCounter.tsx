import { Tooltip } from '@mui/material';

import { getStatusColors, type SeverityCode } from '@centreon/ui';

import numeral from 'numeral';
import { makeStyles } from 'tss-react/mui';

export interface StyleProps {
  severityCode?: SeverityCode | null;
}

const useStyles = makeStyles<StyleProps>()((theme, { severityCode }) => {
  const statusColors = severityCode
    ? getStatusColors({ severityCode, theme })
    : null;

  return {
    container: {
      alignItems: 'center',
      color: theme.palette.mode === 'dark' ? '#EAEEF7' : '#1B2233',
      cursor: 'pointer',
      display: 'inline-flex',
      gap: '3px'
    },
    count: {
      fontFamily: 'ui-monospace, "Roboto Mono", Menlo, monospace',
      fontSize: '12px',
      fontWeight: theme.typography.fontWeightMedium,
      lineHeight: 1
    },
    dot: {
      backgroundColor: statusColors?.backgroundColor ?? theme.palette.divider,
      // Spec (MON-204219 top banner redesign) calls for a "square + clip-path"
      // status pastille rather than a circle, but doesn't give an exact clip-path
      // shape. Using a softly-rounded square as a placeholder — verify the
      // intended shape with the design team before this ships.
      clipPath:
        'polygon(20% 0%, 80% 0%, 100% 20%, 100% 80%, 80% 100%, 20% 100%, 0% 80%, 0% 20%)',
      flexShrink: 0,
      height: '9px',
      width: '9px'
    }
  };
});

export interface Props {
  className?: string;
  count: string | number;
  severityCode?: SeverityCode | null;
}

const StatusCounter = ({
  severityCode = null,
  count,
  className
}: Props): JSX.Element => {
  const { classes, cx } = useStyles({ severityCode });
  const shouldDisableTooltip = Number(count) < 1000;
  const formattedCount = numeral(count).format('0.[0]a');

  return (
    <Tooltip
      disableHoverListener={shouldDisableTooltip}
      followCursor
      title={count}
    >
      <span className={cx(classes.container, className)}>
        <span className={classes.dot} />
        <span className={classes.count}>{formattedCount}</span>
      </span>
    </Tooltip>
  );
};

export default StatusCounter;
