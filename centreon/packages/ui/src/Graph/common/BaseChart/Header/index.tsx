import Typography from '@mui/material/Typography';

import { useMemoComponent } from '@centreon/ui';

import { LineChartHeader } from './models';
import { ussHeaderChartStyles } from './useHeaderStyles';

interface Props {
  header?: LineChartHeader;
  title: string;
}

const Header = ({ title, header }: Props): JSX.Element => {
  const { classes } = ussHeaderChartStyles();

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
