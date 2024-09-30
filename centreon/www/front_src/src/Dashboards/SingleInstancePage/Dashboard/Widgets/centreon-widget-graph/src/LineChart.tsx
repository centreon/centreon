import { useAtomValue } from 'jotai';
import {
  F,
  T,
  always,
  cond,
  equals,
  head,
  identity,
  lensPath,
  pluck,
  set
} from 'ramda';

import {
  BarChart,
  LineChart,
  LineChartData,
  useGraphQuery,
  useRefreshInterval
} from '@centreon/ui';
import { isOnPublicPageAtom } from '@centreon/ui-context';

import NoResources from '../../NoResources';
import { CommonWidgetProps, Data } from '../../models';
import useThresholds from '../../useThresholds';
import { areResourcesFullfilled, getWidgetEndpoint } from '../../utils';

import { graphEndpoint } from './api/endpoints';
import { PanelOptions } from './models';

const forceStackedMetrics = (data?: LineChartData): LineChartData | undefined =>
  data && {
    ...data,
    metrics: data.metrics.map(set(lensPath(['ds_data', 'ds_stack']), true))
  };

interface Props
  extends Pick<
    CommonWidgetProps<PanelOptions>,
    | 'globalRefreshInterval'
    | 'refreshCount'
    | 'dashboardId'
    | 'id'
    | 'playlistHash'
    | 'widgetPrefixQuery'
    | 'isFromPreview'
  > {
  panelData: Data;
  panelOptions: PanelOptions;
}

const WidgetLineChart = ({
  panelData,
  panelOptions,
  globalRefreshInterval,
  refreshCount,
  dashboardId,
  playlistHash,
  id,
  widgetPrefixQuery,
  isFromPreview
}: Props): JSX.Element => {
  const isOnPublicPage = useAtomValue(isOnPublicPageAtom);
  const refreshIntervalToUse = useRefreshInterval({
    globalRefreshInterval,
    refreshInterval: panelOptions.refreshInterval,
    refreshIntervalCustom: panelOptions.refreshIntervalCustom
  });

  const metricNames = pluck('name', panelData.metrics);
  const isLineChart = equals(panelOptions.displayType || 'line', 'line');

  const areResourcesOk = areResourcesFullfilled(panelData.resources);

  const { graphData, start, end, isGraphLoading, isMetricsEmpty } =
    useGraphQuery({
      baseEndpoint: getWidgetEndpoint({
        dashboardId,
        defaultEndpoint: graphEndpoint,
        isOnPublicPage,
        playlistHash,
        widgetId: id
      }),
      bypassQueryParams: isOnPublicPage,
      metrics: panelData.metrics,
      prefix: widgetPrefixQuery,
      refreshCount,
      refreshInterval: refreshIntervalToUse,
      resources: panelData.resources,
      timePeriod: panelOptions.timeperiod
    });

  const formattedThresholds = useThresholds({
    data: graphData,
    metricName: head(metricNames),
    thresholds: panelOptions.threshold
  });

  if (!areResourcesOk || isMetricsEmpty) {
    return <NoResources />;
  }

  const formattedShowArea = cond([
    [equals('auto'), always(undefined)],
    [equals('show'), T],
    [equals('hide'), F]
  ])(panelOptions.showArea);

  const commonProperties = {
    axis: {
      gridLinesType: panelOptions.gridLinesType,
      isCenteredZero: panelOptions.isCenteredZero,
      scale: panelOptions.scale,
      scaleLogarithmicBase: Number(panelOptions.scaleLogarithmicBase),
      showBorder: panelOptions.showAxisBorder,
      showGridLines: panelOptions.showGridLines,
      yAxisTickLabelRotation: panelOptions.yAxisTickLabelRotation
    },
    data: equals(panelOptions.displayType, 'bar-stacked')
      ? forceStackedMetrics(graphData)
      : graphData,
    end,
    height: null,
    legend: {
      display: panelOptions.showLegend,
      mode: panelOptions.legendDisplayMode,
      placement: panelOptions.legendPlacement
    },
    loading: isGraphLoading,
    start,
    thresholdUnit: panelData.metrics[0]?.unit,
    thresholds: formattedThresholds,
    timeShiftZones: {
      enable: false
    },
    tooltip: {
      mode: panelOptions.tooltipMode,
      sortOrder: panelOptions.tooltipSortOrder
    },
    zoomPreview: {
      enable: false
    },
    skipIntersectionObserver: isFromPreview
  };

  if (isLineChart) {
    return (
      <LineChart
        lineStyle={{
          areaTransparency: formattedShowArea
            ? 100 - panelOptions.areaOpacity
            : undefined,
          curve: panelOptions.curveType,
          dashLength: equals(panelOptions.lineStyleMode, 'dash')
            ? panelOptions.dashLength
            : undefined,
          dashOffset: equals(panelOptions.lineStyleMode, 'dash')
            ? panelOptions.dashOffset
            : undefined,
          dotOffset: equals(panelOptions.lineStyleMode, 'dots')
            ? panelOptions.dotOffset
            : undefined,
          lineWidth: equals(panelOptions.lineWidthMode, 'auto')
            ? undefined
            : panelOptions.lineWidth,
          showArea: formattedShowArea,
          showPoints: panelOptions.showPoints
        }}
        {...commonProperties}
      />
    );
  }

  const barChartOrientation = cond([
    [equals('horizontal'), always('vertical')],
    [equals('vertical'), always('horizontal')],
    [T, identity]
  ])(panelOptions.orientation) as 'auto' | 'horizontal' | 'vertical';

  return (
    <BarChart
      {...commonProperties}
      barStyle={{
        opacity: (panelOptions.barOpacity ?? 100) / 100,
        radius: (panelOptions.barRadius ?? 20) / 200
      }}
      orientation={barChartOrientation}
    />
  );
};

export default WidgetLineChart;
