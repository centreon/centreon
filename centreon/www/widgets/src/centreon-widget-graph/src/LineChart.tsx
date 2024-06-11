import { always, cond, equals, F, head, pluck, T } from 'ramda';
import { useAtomValue } from 'jotai';

import { LineChart, useGraphQuery, useRefreshInterval } from '@centreon/ui';
import { isOnPublicPageAtom } from '@centreon/ui-context';

import useThresholds from '../../useThresholds';
import { CommonWidgetProps, Data } from '../../models';
import NoResources from '../../NoResources';
import { areResourcesFullfilled, getWidgetEndpoint } from '../../utils';

import { PanelOptions } from './models';
import { graphEndpoint } from './api/endpoints';

interface Props
  extends Pick<
    CommonWidgetProps<PanelOptions>,
    | 'globalRefreshInterval'
    | 'refreshCount'
    | 'dashboardId'
    | 'id'
    | 'playlistHash'
    | 'widgetPrefixQuery'
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
  widgetPrefixQuery
}: Props): JSX.Element => {
  const isOnPublicPage = useAtomValue(isOnPublicPageAtom);
  const refreshIntervalToUse = useRefreshInterval({
    globalRefreshInterval,
    refreshInterval: panelOptions.refreshInterval,
    refreshIntervalCustom: panelOptions.refreshIntervalCustom
  });

  const metricNames = pluck('name', panelData.metrics);

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

  return (
    <LineChart
      axis={{
        gridLinesType: panelOptions.gridLinesType,
        isCenteredZero: panelOptions.isCenteredZero,
        scale: panelOptions.scale,
        scaleLogarithmicBase: Number(panelOptions.scaleLogarithmicBase),
        showBorder: panelOptions.showAxisBorder,
        showGridLines: panelOptions.showGridLines,
        yAxisTickLabelRotation: panelOptions.yAxisTickLabelRotation
      }}
      data={graphData}
      end={end}
      height={null}
      legend={{
        display: panelOptions.showLegend,
        mode: panelOptions.legendDisplayMode,
        placement: panelOptions.legendPlacement
      }}
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
      loading={isGraphLoading}
      start={start}
      thresholdUnit={panelData.metrics[0]?.unit}
      thresholds={formattedThresholds}
      timeShiftZones={{
        enable: false
      }}
      tooltip={{
        mode: panelOptions.tooltipMode,
        sortOrder: panelOptions.tooltipSortOrder
      }}
      zoomPreview={{
        enable: false
      }}
    />
  );
};

export default WidgetLineChart;
