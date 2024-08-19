import { useEffect, useState } from 'react';

import { Meta, StoryObj } from '@storybook/react';

import { Button } from '@mui/material';
import ButtonGroup from '@mui/material/ButtonGroup';
import Switch from '@mui/material/Switch';
import Tooltip from '@mui/material/Tooltip';

import { useLocaleDateTimeFormat } from '@centreon/ui';

import TimePeriod from '../../TimePeriods';
import { LineChartData } from '../common/models';
import annotationData from '../mockedData/annotationData.json';
import exclusionPeriodFirstPeriod from '../mockedData/exclusionPeriodFirstPeriod.json';
import exclusionPeriodSecondPeriod from '../mockedData/exclusionPeriodSecondPeriod.json';
import exclusionPeriodThirdPeriod from '../mockedData/exclusionPeriodThirdPeriod.json';
import dataLastDayForword from '../mockedData/lastDayForward.json';
import dataLastDayThreshold from '../mockedData/lastDayThreshold.json';
import dataLastMonth from '../mockedData/lastMonth.json';
import dataLastWeek from '../mockedData/lastWeek.json';
import dataZoomPreview from '../mockedData/zoomPreview.json';
import dataLastDay from '../mockedData/lastDay.json';
import dataCurvesSameColor from '../mockedData/curvesWithSameColor.json';
import dataLastDayWithLotOfUnits from '../mockedData/lastDayWithLotOfUnits.json';
import dataPingServiceLinesBars from '../mockedData/pingServiceLinesBars.json';
import dataPingServiceLinesBarsStacked from '../mockedData/pingServiceLinesBarsStacked.json';
import dataPingServiceLinesBarsMixed from '../mockedData/pingServiceLinesBarsMixed.json';

import {
  argTypes,
  args as argumentsData,
  defaultEnd,
  defaultLast7days,
  defaultLastMonth,
  defaultStart,
  lastDayForwardDate,
  zoomPreviewDate
} from './helpers/doc';
import { dateTimeFormat } from './common';
import { Interval, ThresholdType, TooltipData } from './models';

import WrapperChart from '.';

const meta: Meta<typeof WrapperChart> = {
  component: WrapperChart
};
export default meta;

type Story = StoryObj<typeof WrapperChart>;

interface Random {
  max: number;
  min: number;
}

const Threshold = (args): JSX.Element => {
  const [currentFactorMultiplication, setCurrentFactorMultiplication] =
    useState<number>(2.5);
  const [countedCircles, setCountedCircles] = useState<number>();

  const getRandomInt = ({ min, max }: Random): number => {
    return Math.floor(Math.random() * (max - min) + min);
  };

  const handleClick = (): void => {
    setCurrentFactorMultiplication(getRandomInt({ max: 5, min: 1 }));
  };

  const getCountDisplayedCircles = (value: number): void => {
    setCountedCircles(value);
  };

  return (
    <>
      <Tooltip title={`number of displayed circles :${countedCircles}`}>
        <Button onClick={handleClick}>change envelope size randomly</Button>
      </Tooltip>

      <WrapperChart
        {...args}
        data={dataLastDayThreshold}
        shapeLines={{
          areaThresholdLines: [
            { type: ThresholdType.basic },
            {
              factors: {
                currentFactorMultiplication,
                simulatedFactorMultiplication: 1.5
              },
              getCountDisplayedCircles,
              type: ThresholdType.variation
            }
          ]
        }}
      />
    </>
  );
};

const ExternalComponent = (tooltipData): JSX.Element => {
  const { hideTooltip, data } = tooltipData;
  const { format } = useLocaleDateTimeFormat();

  return (
    <>
      External component
      <br />
      <br />
      {format({
        date: data,
        formatString: dateTimeFormat
      })}
      <br />
      <br />
      <button type="button" onClick={(): void => hideTooltip()}>
        hide tooltip
      </button>
    </>
  );
};

const LineChartAndCLS = (args): JSX.Element => {
  const [data, setData] = useState<LineChartData | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    setTimeout(() => {
      setLoading(false);
      setData(dataLastDayThreshold as unknown as LineChartData);
    }, 100000);
  }, []);

  return (
    <WrapperChart
      {...args}
      data={data}
      loading={loading}
      shapeLines={{ areaThresholdLines: [{ type: ThresholdType.basic }] }}
    />
  );
};

interface TimePeriodSwitchProps {
  getDataSwitch: (value: boolean) => void;
}

const TimePeriodSwitch = ({
  getDataSwitch
}: TimePeriodSwitchProps): JSX.Element => {
  const [checked, setChecked] = useState(false);

  const handleChange = (event: React.ChangeEvent<HTMLInputElement>): void => {
    setChecked(event.target.checked);
  };

  useEffect(() => {
    getDataSwitch?.(checked);
  }, [checked]);

  return (
    <Switch
      checked={checked}
      inputProps={{ 'aria-label': 'controlled' }}
      onChange={handleChange}
    />
  );
};

const LineChartAndTimePeriod = (args): JSX.Element => {
  const [currentData, setCurrentData] = useState<LineChartData>();
  const [start, setStart] = useState<string>();
  const [end, setEnd] = useState<string>();
  const [displayAnnotation, setDisplayAnnotation] = useState();
  const [adjustedTimePeriodInterval, setAdjustedTimePeriodInterval] =
    useState<Interval>();

  const getParameters = (interval): void => {
    setStart(interval.start);
    setEnd(interval.end);
  };

  useEffect(() => {
    if (!start || !end) {
      return;
    }
    if (start.includes(lastDayForwardDate)) {
      setCurrentData(dataLastDayForword as unknown as LineChartData);

      return;
    }

    if (start.includes(`${defaultStart.split('T')[0]}`)) {
      setCurrentData(dataLastDayThreshold as unknown as LineChartData);

      return;
    }
    if (start.includes(defaultLast7days.split('T')[0])) {
      setCurrentData(dataLastWeek as unknown as LineChartData);

      return;
    }
    if (start.includes(defaultLastMonth.split('T')[0])) {
      setCurrentData(dataLastMonth as unknown as LineChartData);

      return;
    }
    if (start.includes(zoomPreviewDate)) {
      setCurrentData(dataZoomPreview as unknown as LineChartData);
    }
  }, [start, end, adjustedTimePeriodInterval]);

  const getInterval = (interval: Interval): void => {
    setAdjustedTimePeriodInterval(interval);
  };

  const getDataSwitch = (value): void => {
    setDisplayAnnotation(value);
  };

  const annotationEventData = displayAnnotation && {
    data: annotationData.result
  };

  return (
    <>
      <TimePeriod
        adjustTimePeriodData={adjustedTimePeriodInterval}
        getParameters={getParameters}
        renderExternalComponent={
          <TimePeriodSwitch getDataSwitch={getDataSwitch} />
        }
      />
      <WrapperChart
        data={currentData}
        {...args}
        annotationEvent={annotationEventData}
        end={end}
        loading={false}
        shapeLines={{ areaThresholdLines: [{ type: ThresholdType.basic }] }}
        start={start}
        timeShiftZones={{ enable: true, getInterval }}
        tooltip={{
          enable: true,
          renderComponent: ({
            data,
            tooltipOpen,
            hideTooltip
          }: TooltipData): JSX.Element => (
            <ExternalComponent
              data={data}
              hideTooltip={hideTooltip}
              openTooltip={tooltipOpen}
            />
          )
        }}
        zoomPreview={{ enable: true, getInterval }}
      />
    </>
  );
};

const LineChartAndExclusionPeriod = (args): JSX.Element => {
  const [dataExclusionPeriods, setDataExclusionPeriods] = useState<
    Array<LineChartData>
  >([exclusionPeriodFirstPeriod as unknown as LineChartData]);

  const handleClick = (data): void => {
    setDataExclusionPeriods([...dataExclusionPeriods, data]);
  };

  return (
    <>
      <div>Add exclusion periods:</div>
      <ButtonGroup
        aria-label="outlined primary button group"
        variant="contained"
      >
        <Button onClick={(): void => handleClick(exclusionPeriodSecondPeriod)}>
          first
        </Button>
        <Button onClick={(): void => handleClick(exclusionPeriodThirdPeriod)}>
          second
        </Button>
      </ButtonGroup>
      <WrapperChart
        {...args}
        data={dataLastDayThreshold as unknown as LineChartData}
        shapeLines={{
          areaThresholdLines: [
            {
              type: ThresholdType.basic
            },
            {
              data: dataExclusionPeriods,
              orientation: ['diagonal'],
              type: ThresholdType.pattern
            }
          ]
        }}
      />
    </>
  );
};

const Template: Story = {
  render: (args) => (
    <WrapperChart
      {...args}
      data={dataLastDayThreshold as unknown as LineChartData}
      shapeLines={{
        areaThresholdLines: [
          {
            type: ThresholdType.basic
          }
        ]
      }}
    />
  )
};

const WithTimePeriod = {
  render: (args): JSX.Element => <LineChartAndTimePeriod {...args} />
};

const LineChartWithExclusionPeriod: Story = {
  render: (args) => <LineChartAndExclusionPeriod {...args} />
};

const LineChartWithEnvelopVariation: Story = {
  render: (args) => <Threshold {...args} />
};

const LineChartWithCLS: Story = {
  render: (args) => <LineChartAndCLS {...args} />
};

export const LineChart: Story = {
  ...Template,
  argTypes,
  args: argumentsData
};

export const LineChartWithStepCurve: Story = {
  ...Template,
  argTypes,
  args: {
    ...argumentsData,
    lineStyle: {
      curve: 'step'
    }
  }
};

export const LineChartWithTimePeriod: Story = {
  ...WithTimePeriod,
  args: {
    end: defaultEnd,
    height: 500,
    start: defaultStart
  },
  parameters: {
    chromatic: { disableSnapshot: true }
  }
};

export const WithEnvelopVariation: Story = {
  ...LineChartWithEnvelopVariation,
  args: {
    end: defaultEnd,
    height: 500,
    start: defaultStart
  }
};

export const withExclusionPeriods: Story = {
  ...LineChartWithExclusionPeriod,
  args: {
    end: defaultEnd,
    height: 500,
    start: defaultStart
  }
};

export const withCLS: Story = {
  ...LineChartWithCLS,
  args: {
    end: defaultEnd,
    height: 500,
    start: defaultStart
  }
};

export const withThresholds: Story = {
  argTypes,
  args: {
    ...argumentsData,
    thresholds: {
      critical: [
        {
          label: 'critical',
          value: 350
        }
      ],
      enabled: true,
      warning: [
        {
          label: 'warning',
          value: 300
        }
      ]
    }
  },
  render: (args) => (
    <WrapperChart {...args} data={dataLastDay as unknown as LineChartData} />
  )
};

export const withThresholdsAndUnit: Story = {
  argTypes,
  args: {
    ...argumentsData,
    thresholdUnit: '%',
    thresholds: {
      critical: [
        {
          label: 'critical',
          value: 79
        }
      ],
      enabled: true,
      warning: [
        {
          label: 'warning',
          value: 65
        }
      ]
    }
  },
  render: (args) => (
    <WrapperChart {...args} data={dataLastDay as unknown as LineChartData} />
  )
};

export const thresholdsRange: Story = {
  argTypes,
  args: {
    ...argumentsData,
    thresholdUnit: '%',
    thresholds: {
      critical: [
        {
          label: 'critical 1',
          value: 60
        },
        {
          label: 'critical 2',
          value: 79
        }
      ],
      enabled: true,
      warning: [
        {
          label: 'warning 1',
          value: 20
        },
        {
          label: 'warning 2',
          value: 30
        }
      ]
    }
  },
  render: (args) => (
    <WrapperChart {...args} data={dataLastDay as unknown as LineChartData} />
  )
};

export const LineChartWithSameColorCurves: Story = {
  ...Template,
  argTypes,
  args: argumentsData,
  render: (args) => (
    <WrapperChart
      {...args}
      data={dataCurvesSameColor as unknown as LineChartData}
    />
  )
};

export const zeroCentered: Story = {
  argTypes,
  args: {
    ...argumentsData,
    axis: {
      isCenteredZero: true
    }
  },
  render: (args) => (
    <WrapperChart {...args} data={dataLastDay as unknown as LineChartData} />
  )
};

export const customLines: Story = {
  argTypes,
  args: {
    ...argumentsData,
    lineStyle: {
      areaTransparency: 10,
      dashLength: 10,
      dashOffset: 10,
      lineWidth: 3,
      showArea: true,
      showPoints: true
    }
  },
  render: (args) => (
    <WrapperChart {...args} data={dataLastDay as unknown as LineChartData} />
  )
};

export const customLinesAndBars: Story = {
  argTypes,
  args: {
    ...argumentsData,
    barStyle: {
      opacity: 0.5,
      radius: 0.5
    },
    lineStyle: {
      curve: 'natural',
      lineWidth: 2,
      showPoints: true
    }
  },
  render: (args) => (
    <WrapperChart
      {...args}
      data={dataPingServiceLinesBars as unknown as LineChartData}
    />
  )
};

export const multipleUnits: Story = {
  argTypes,
  args: argumentsData,
  render: (args) => (
    <WrapperChart
      {...args}
      data={dataLastDayWithLotOfUnits as unknown as LineChartData}
    />
  )
};

export const linesAndBars: Story = {
  argTypes,
  args: {
    ...argumentsData,
    lineStyle: {
      curve: 'natural',
      lineWidth: 2,
      showPoints: true
    }
  },
  render: (args) => (
    <WrapperChart
      {...args}
      data={dataPingServiceLinesBars as unknown as LineChartData}
    />
  )
};

export const linesAndBarsStacked: Story = {
  argTypes,
  args: {
    ...argumentsData,
    lineStyle: {
      curve: 'natural',
      lineWidth: 2,
      showPoints: false
    }
  },
  render: (args) => (
    <WrapperChart
      {...args}
      data={dataPingServiceLinesBarsStacked as unknown as LineChartData}
    />
  )
};

export const linesAndBarsMixed: Story = {
  argTypes,
  args: {
    ...argumentsData,
    lineStyle: {
      curve: 'natural',
      lineWidth: 2,
      showPoints: false
    }
  },
  render: (args) => (
    <WrapperChart
      {...args}
      data={dataPingServiceLinesBarsMixed as unknown as LineChartData}
    />
  )
};

export const linesAndBarsCenteredZero: Story = {
  argTypes,
  args: {
    ...argumentsData,
    axis: {
      isCenteredZero: true
    },
    lineStyle: {
      curve: 'natural',
      lineWidth: 2,
      showPoints: true
    }
  },
  render: (args) => (
    <WrapperChart
      {...args}
      data={dataPingServiceLinesBars as unknown as LineChartData}
    />
  )
};

export const linesAndBarsStackedCenteredZero: Story = {
  argTypes,
  args: {
    ...argumentsData,
    axis: {
      isCenteredZero: true
    },
    lineStyle: {
      curve: 'natural',
      lineWidth: 2,
      showPoints: false
    }
  },
  render: (args) => (
    <WrapperChart
      {...args}
      data={dataPingServiceLinesBarsStacked as unknown as LineChartData}
    />
  )
};

export const linesAndBarsMixedCenteredZero: Story = {
  argTypes,
  args: {
    ...argumentsData,
    axis: {
      isCenteredZero: true
    },
    lineStyle: {
      curve: 'natural',
      lineWidth: 2,
      showPoints: false
    }
  },
  render: (args) => (
    <WrapperChart
      {...args}
      data={dataPingServiceLinesBarsMixed as unknown as LineChartData}
    />
  )
};
const CustomYUnits = (props): JSX.Element => {
  const [leftUnit, setLeftUnit] = useState('b');
  const [rightUnit, setRightUnit] = useState('ms');

  return (
    <WrapperChart
      {...props}
      axis={{
        axisYLeft: {
          onUnitChange: setLeftUnit,
          unit: leftUnit
        },
        axisYRight: {
          onUnitChange: setRightUnit,
          unit: rightUnit
        }
      }}
      data={dataPingServiceLinesBars}
    />
  );
};

export const customYUnits: Story = {
  argTypes,
  args: argumentsData,
  render: (args) => <CustomYUnits {...args} />
};
