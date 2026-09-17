import Typography from '@mui/material/Typography';

import { useMemoComponent } from '@centreon/ui';

import type { ReactElement } from 'react';

import type { LineChartHeader } from './models';
import { ussHeaderChartStyles } from './useHeaderStyles';

interface Props {
  header?: LineChartHeader;
  title: string;
}

const Header = ({ title, header }: Props): ReactElement => {
  const { classes } = ussHeaderChartStyles();

  const displayTitle = header?.displayTitle ?? true;

  return useMemoComponent({
    Component: (
      <div className={classes.header}>
        <div />
        {displayTitle && (
          <Typography align="center" className={classes.title} variant="body1">
            {title}
          </Typography>
        )}
        {header?.extraComponent}
      </div>
    ),

    memoProps: [title, header]
  });
};

export default Header;
