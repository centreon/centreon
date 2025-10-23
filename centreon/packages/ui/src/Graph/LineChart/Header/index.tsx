import { useMemoComponent } from '@centreon/ui';

import Typography from '@mui/material/Typography';
import type { ReactElement } from 'react';

import { useStyles } from '../LineChart.styles';
import type { LineChartHeader } from '../models';

interface Props {
  header?: LineChartHeader;
  title: string;
}

const Header = ({ title, header }: Props): ReactElement => {
  const { classes } = useStyles();

  const displayTitle = header?.displayTitle ?? true;

  return useMemoComponent({
    Component: (
      <div className={classes.header}>
        <div />
        <div>
          {displayTitle && (
            <Typography align="center" variant="body1">
              {title}
            </Typography>
          )}
        </div>
        {header?.extraComponent}
      </div>
    ),

    memoProps: [title, header]
  });
};

export default Header;
