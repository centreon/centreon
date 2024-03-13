import { equals, isNil } from 'ramda';

import { PieChart, BarStack } from '@centreon/ui';

import { ChartType, DisplayType } from '../models';
import useLoadResources from '../useLoadResources';
import { goToUrl } from '../../../utils';
import Legend from '../Legend/Legend';
import TooltipContent from '../Tooltip/Tooltip';

import { useStyles } from './Chart.styles';
import ChartSkeleton from './LoadingSkeleton';

const Chart = ({
  displayType,
  displayLegend,
  displayValues,
  resourceType,
  unit,
  title,
  refreshCount,
  refreshIntervalToUse,
  resources,
  labelNoDataFound,
  getLinkToResourceStatusPage,
  isHorizontalBar,
  isSingleChart
}: ChartType): JSX.Element => {
  const { cx, classes } = useStyles();

  const isPieCharts =
    equals(displayType, DisplayType.Pie) ||
    equals(displayType, DisplayType.Donut);

  const { data, isLoading } = useLoadResources({
    refreshCount,
    refreshIntervalToUse,
    resourceType,
    resources
  });

  const goToResourceStatusPage = (status): void => {
    const url = getLinkToResourceStatusPage(status, resourceType);

    goToUrl(url)();
  };

  if (isLoading && isNil(data)) {
    return <ChartSkeleton />;
  }

  if (isNil(data)) {
    return <div />;
  }

  return (
    <div className={classes.container}>
      {isPieCharts ? (
        <div
          className={cx(classes.pieChart, {
            [classes.singlePieChart]: isSingleChart
          })}
        >
          <PieChart
            Legend={Legend((status) =>
              getLinkToResourceStatusPage(status, resourceType)
            )}
            TooltipContent={TooltipContent}
            data={data}
            displayLegend={displayLegend}
            displayValues={displayValues}
            labelNoDataFound={labelNoDataFound}
            title={title}
            unit={unit}
            variant={displayType as 'pie' | 'donut'}
            onArcClick={({ label: status }) => {
              goToResourceStatusPage(status);
            }}
          />
        </div>
      ) : (
        <div
          className={cx(classes.barStack, {
            [classes.verticalBar]: !isHorizontalBar,
            [classes.singleHorizontalBar]: isHorizontalBar && isSingleChart
          })}
        >
          <BarStack
            Legend={Legend((status) =>
              getLinkToResourceStatusPage(status, resourceType)
            )}
            TooltipContent={TooltipContent}
            data={data}
            displayLegend={displayLegend}
            displayValues={displayValues}
            labelNoDataFound={labelNoDataFound}
            legendDirection={isHorizontalBar ? 'row' : 'column'}
            size={80}
            title={title}
            unit={unit}
            variant={displayType as 'horizontal' | 'vertical'}
            onSingleBarClick={({ key: status }) => {
              goToResourceStatusPage(status);
            }}
          />
        </div>
      )}
    </div>
  );
};

export default Chart;
