import { useEffect, useState } from 'react';

import { Meta, StoryObj } from '@storybook/react';

import AwesomeTimePeriod from '../TimePeriods';

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
import dataLastDay from './mockedData/lastDay.json';
import dataLastDayForword from './mockedData/lastDayForward.json';
import dataLastMonth from './mockedData/lastMonth.json';
import dataLastWeek from './mockedData/lastWeek.json';
import dataZoomPreview from './mockedData/zoomPreview.json';
import { GraphData, Interval } from './models';

import WraperGraph from './index';

const meta: Meta<typeof WraperGraph> = {
  component: WraperGraph,
  tags: ['autodocs']
};
export default meta;

type Story = StoryObj<typeof WraperGraph>;

const Template: Story = {
  render: (args) => <WraperGraph {...args} data={dataLastDay} />
};

export const Graph: Story = {
  ...Template,
  argTypes,
  args: argumentsData
};

const GraphAndTimePeriod = (args): JSX.Element => {
  const [currentData, setCurrentData] = useState<GraphData>();
  const [start, setStart] = useState();
  const [end, setEnd] = useState();
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

  const getZoomInterval = (interval: Interval): void => {
    setAdjustedTimePeriodInterval(interval);
  };

  const getInterval = (interval: Interval): void => {
    setAdjustedTimePeriodInterval(interval);
  };

  return (
    <>
      <AwesomeTimePeriod
        adjustTimePeriodData={adjustedTimePeriodInterval}
        getStartEndParameters={getParameters}
      />
      <WraperGraph
        data={currentData}
        {...args}
        end={end}
        loading={false}
        start={start}
        timeShiftZones={{ enable: true, getInterval }}
        zoomPreview={{ getZoomInterval }}
      />
    </>
  );
};

const WithTimePeriod = {
  render: (args): JSX.Element => <GraphAndTimePeriod {...args} />
};

export const GraphWithTimePeriod: Story = {
  ...WithTimePeriod,
  args: {
    end: defaultEnd,
    height: 500,
    start: defaultStart
  }
};
