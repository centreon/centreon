import { ReactNode } from 'react';
import { useTopBottomStyles } from './TopBottom.styles';

interface Props {
  children: ReactNode;
  singleBarCurrentWidth: number | string;
}

const MetricContainer = ({ children, singleBarCurrentWidth }: Props) => {
  const { classes } = useTopBottomStyles({ singleBarCurrentWidth });

  return <div className={classes.metricTopContainer}>{children}</div>;
};

export default MetricContainer;
