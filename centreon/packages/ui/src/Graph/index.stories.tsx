import { useEffect, useState } from 'react';

import { Meta, StoryObj } from '@storybook/react';

import { Button } from '@mui/material';
import ButtonGroup from '@mui/material/ButtonGroup';
import Switch from '@mui/material/Switch';
import Tooltip from '@mui/material/Tooltip';

import { useLocaleDateTimeFormat } from '@centreon/ui';

import AwesomeTimePeriod from '../TimePeriods';

import { dateTimeFormat } from './common';
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
import annotationData from './mockedData/annotationData.json';
import exclusionPeriodFirstPeriod from './mockedData/exclusionPeriodFirstPeriod.json';
import exclusionPeriodFourthPeriod from './mockedData/exclusionPeriodFourthPeriod.json';
import exclusionPeriodSecondPeriod from './mockedData/exclusionPeriodSecondPeriod.json';
import dataLastDayForword from './mockedData/lastDayForward.json';
import dataLastDay from './mockedData/lastDayThreshold.json';
import dataLastMonth from './mockedData/lastMonth.json';
import dataLastWeek from './mockedData/lastWeek.json';
import dataZoomPreview from './mockedData/zoomPreview.json';
import { GraphData, Interval, TooltipData } from './models';

import WrapperGraph from './index';

const meta: Meta<typeof WrapperGraph> = {
  component: WrapperGraph,
  tags: ['autodocs']
};
export default meta;

type Story = StoryObj<typeof WrapperGraph>;

interface Random {
  max: number;
  min: number;
}

const Threshold = (args): JSX.Element => {
  const [currentFactorMultiplication, setCurrentFactorMultiplication] =
    useState<number>();
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

      <WrapperGraph
        {...args}
        data={dataLastDay}
        shapeLines={{
          areaThresholdLines: {
            display: true,
            factors: {
              currentFactorMultiplication,
              simulatedFactorMultiplication: 1.5
            },
            getCountDisplayedCircles
          }
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
        date: new Date(data),
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

const GraphAndCLS = (args): JSX.Element => {
  const [data, setData] = useState();
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    setTimeout(() => {
      setLoading(false);
      setData(dataLastDay);
    }, 1000);
  }, []);

  return (
    <WrapperGraph
      {...args}
      data={data}
      loading={loading}
      shapeLines={{ areaThresholdLines: { display: true } }}
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

const GraphAndTimePeriod = (args): JSX.Element => {
  const [currentData, setCurrentData] = useState<GraphData>();
  const [start, setStart] = useState();
  const [end, setEnd] = useState();
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
      setCurrentData(dataLastDayForword);

      return;
    }

    if (start.includes(`${defaultStart.split('T')[0]}`)) {
      setCurrentData(dataLastDay);

      return;
    }
    if (start.includes(defaultLast7days.split('T')[0])) {
      setCurrentData(dataLastWeek);

      return;
    }
    if (start.includes(defaultLastMonth.split('T')[0])) {
      setCurrentData(dataLastMonth);

      return;
    }
    if (start.includes(zoomPreviewDate)) {
      setCurrentData(dataZoomPreview);
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
      <AwesomeTimePeriod
        adjustTimePeriodData={adjustedTimePeriodInterval}
        getParameters={getParameters}
        renderExternalComponent={
          <TimePeriodSwitch getDataSwitch={getDataSwitch} />
        }
      />
      <WrapperGraph
        data={currentData}
        {...args}
        annotationEvent={annotationEventData}
        end={end}
        loading={false}
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

const GraphAndExclusionPeriod = (args): JSX.Element => {
  const [dataExclusionPeriods, setDataExclusionPeriods] = useState([]);

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
        <Button onClick={(): void => handleClick(exclusionPeriodFirstPeriod)}>
          first
        </Button>
        <Button onClick={(): void => handleClick(exclusionPeriodSecondPeriod)}>
          second
        </Button>
        <Button onClick={(): void => handleClick(exclusionPeriodFourthPeriod)}>
          third
        </Button>
      </ButtonGroup>
      <WrapperGraph
        {...args}
        data={dataLastDay as unknown as GraphData}
        shapeLines={{
          areaThresholdLines: {
            dataExclusionPeriods,
            display: true
          }
        }}
      />
    </>
  );
};

const Template: Story = {
  render: (args) => (
    <WrapperGraph {...args} data={dataLastDay as unknown as GraphData} />
  )
};

export const Graph: Story = {
  ...Template,
  argTypes,
  args: argumentsData
};

const WithTimePeriod = {
  render: (args): JSX.Element => <GraphAndTimePeriod {...args} />
};

const GraphWithExclusionPeriod: Story = {
  render: (args) => <GraphAndExclusionPeriod {...args} />
};

export const GraphWithTimePeriod: Story = {
  ...WithTimePeriod,
  args: {
    end: defaultEnd,
    height: 500,
    shapeLines: {
      areaThresholdLines: {
        display: true
      }
    },
    start: defaultStart
  }
};

const GraphWithEnvelopVariation: Story = {
  render: (args) => <Threshold {...args} />
};

export const WithEnvelopVariation: Story = {
  ...GraphWithEnvelopVariation,
  args: {
    end: defaultEnd,
    height: 500,
    shapeLines: {
      areaThresholdLines: {
        display: true
      }
    },
    start: defaultStart
  }
};

export const withExclusionPeriods: Story = {
  ...GraphWithExclusionPeriod,
  args: {
    end: defaultEnd,
    height: 500,
    start: defaultStart
  }
};

const GraphWithCLS: Story = {
  render: (args) => <GraphAndCLS {...args} />
};

export const withCLS: Story = {
  ...GraphWithCLS,
  args: {
    end: defaultEnd,
    height: 500,
    start: defaultStart
  }
};
