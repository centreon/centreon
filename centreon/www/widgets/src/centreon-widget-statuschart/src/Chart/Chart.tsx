import { equals, isEmpty, isNil, reject } from 'ramda';
import { useTranslation } from 'react-i18next';

import { PieChart, BarStack } from '@centreon/ui';

import { ChartType, DisplayType } from '../models';
import useLoadResources from '../useLoadResources';
import { goToUrl } from '../../../utils';
import Legend from '../Legend/Legend';
import TooltipContent from '../Tooltip/Tooltip';
import { NoResourcesFound } from '../../../NoResourcesFound';
import {
  labelNoHostsFound,
  labelNoServicesFound
} from '../../../translatedLabels';

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
  getLinkToResourceStatusPage,
  isHorizontalBar,
  isSingleChart
}: ChartType): JSX.Element => {
  const { cx, classes } = useStyles();
  const { t } = useTranslation();

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

  const areAllValuesNull = isEmpty(
    reject(({ value }) => equals(value, 0), data)
  );

  if (areAllValuesNull) {
    return (
      <NoResourcesFound
        label={
          equals(resourceType, 'host')
            ? t(labelNoHostsFound)
            : t(labelNoServicesFound)
        }
      />
    );
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
            title={title}
            tooltipProps={{ resources }}
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
            legendDirection={isHorizontalBar ? 'row' : 'column'}
            size={80}
            title={title}
            tooltipProps={{ resources }}
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
