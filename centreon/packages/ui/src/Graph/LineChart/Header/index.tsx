import Typography from '@mui/material/Typography';

import { useMemoComponent } from '@centreon/ui';

import { useLineChartStyles } from '../LineChart.styles';
import { LineChartHeader } from '../models';

interface Props {
  header?: LineChartHeader;
  title: string;
}

const Header = ({ title, header }: Props): JSX.Element => {
  const { classes } = useLineChartStyles();

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
