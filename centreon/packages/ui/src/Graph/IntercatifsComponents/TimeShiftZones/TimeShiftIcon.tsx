import { ReactNode } from 'react';

import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import { useMemoComponent } from '@centreon/ui';

export const timeShiftIconSize = 20;

interface Props {
  Icon: ReactNode;
  ariaLabel: string;
  xIcon: number;
  yIcon: number;
}

const useStyles = makeStyles()({
  icon: {
    cursor: 'pointer'
  }
});

const TimeShiftIcon = ({
  xIcon,
  yIcon,
  Icon,
  ariaLabel
}: Props): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const svgProps = {
    'aria-label': t(ariaLabel),
    className: classes.icon,
    height: timeShiftIconSize,
    width: timeShiftIconSize,
    x: xIcon,
    y: yIcon
  };

  return useMemoComponent({
    Component: (
      <g>
        <svg {...svgProps}>
          <rect
            fill="transparent"
            height={timeShiftIconSize}
            width={timeShiftIconSize}
          />
          {Icon}
        </svg>
      </g>
    ),
    memoProps: [xIcon, ariaLabel]
  });
};

export default TimeShiftIcon;
