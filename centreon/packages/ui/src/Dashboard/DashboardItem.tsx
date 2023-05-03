import { ForwardedRef, forwardRef } from 'react';

import { Card } from '@mui/material';
import useMemoComponent from '../utils/useMemoComponent';
import { useDashboardItemStyles } from './useDashboardStyles';

interface DashboardItemProps {
  children: JSX.Element;
  key: string;
}

const DashboardItem = forwardRef(
  (
    { children, key, ...others }: DashboardItemProps,
    ref: ForwardedRef<HTMLDivElement>
  ): JSX.Element => {
    const { classes } = useDashboardItemStyles();

    return useMemoComponent({ Component: (
      <div key={key} ref={ref} {...others}>
        <Card className={classes.widgetContainer}>
          <div className={classes.widgetContent}>{children}</div>
        </Card>
      </div>
    ), memoProps: [key, others]});
  }
);

export default DashboardItem;
