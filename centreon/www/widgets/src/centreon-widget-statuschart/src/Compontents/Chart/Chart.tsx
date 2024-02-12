import { equals } from 'ramda';

import { PieChart, BarStack } from '@centreon/ui';

import { DisplayType, PanelOptions } from '../../models';

const Chart = ({
  displayType,
  states,
  displayLegend,
  displayValues,
  resourceType,
  unit,
  data,
  displayPredominentInformation,
  title
}: Omit<PanelOptions, 'refreshInterval' | 'refreshIntervalCustom'> & {
  data?;
  title?;
}): JSX.Element => {
  return (
    <div
      style={{
        alignItems: 'centrer',
        display: 'flex',
        justifyContent: 'center',
        width: '100%'
      }}
    >
      {equals(displayType, DisplayType.Pie) ||
      equals(displayType, DisplayType.Donut) ? (
        <div style={{ width: '250px' }}>
          <PieChart
            data={data}
            displayLegend={displayLegend}
            displayValues={displayValues}
            legendConfiguration={{ direction: 'column' }}
            title={title}
            unit={unit}
            variant={displayType}
          />
        </div>
      ) : (
        <div style={{ height: '300px', width: '60px' }}>
          <BarStack
            data={data}
            displayLegend={displayLegend}
            displayValues={displayValues}
            legendConfiguration={{ direction: 'column' }}
            title={title}
            unit={unit}
            variant={displayType}
          />
        </div>
      )}
    </div>
  );
};

export default Chart;
