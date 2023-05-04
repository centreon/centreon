import React, { useEffect, useState } from 'react';

import { ComponentMeta, ComponentStory } from '@storybook/react';
import { equals, isEmpty, isNil } from 'ramda';
import dayjs from 'dayjs';

import TimePeriod from '../TimePeriods';

import Graph from './index';

export default {
  component: Graph,
  title: 'Graph'
} as ComponentMeta<typeof Graph>;

const Template: ComponentStory<typeof Graph> = (args) => {
  const { baseUrl, start, end, height } = args;

  const [startTime, setStartTime] = useState<string>('');
  const [endTime, setEndTime] = useState<string>('');

  const getGraphParameters = (data): void => {
    setStartTime(data.start);
    setEndTime(data.end);
  };

  const setTimePeriod = (callback): void => {
    // callback with parameters : timePeriodParameters
    // console.log({ callback });
  };

  useEffect(() => {
    if (equals(typeof start, 'number')) {
      setStartTime(new Date(start).toISOString());
    }

    if (equals(typeof end, 'number')) {
      setEndTime(new Date(start).toISOString());
    }
  }, [start, end]);

  return (
    <>
      <TimePeriod
        getTimePeriodParameters={getGraphParameters}
        setTimePeriod={setTimePeriod}
      />
      <Graph
        {...args}
        baseUrl={baseUrl}
        end={endTime}
        height={height}
        start={startTime}
      />
    </>
  );
};

export const Playground = Template.bind({});

interface InitialValue {
  section: string;
  sectionDescription?: string;
  type: string;
}

const initialValue = ({
  section,
  type,
  sectionDescription = ''
}: InitialValue): string => `
<details>
<summary>${section} ${getCustomText(type)}</summary>

>${sectionDescription}<br>
`;

interface Prop {
  description: string;
  type: string;
}

interface Section {
  description?: string;
  name: string;
  note: string;
  props: Array<Record<string, Prop>>;
  type: string;
}

interface Description {
  sections: Array<Section>;
}

const getCustomText = (text: string): string =>
  `<span style="color:#1EA7FD;fontSize:12px">(${text})</span>`;

const getBodyDescription = ({ key, description, type }): string =>
  `<strong>${key}</strong> : ${description} ${getCustomText(type)} <br>`;

const getNote = ({ componentName, link }): string =>
  `You can use ${componentName} props to override the default props of this component [click here](${link})`;

const getDescription = ({ sections }: Description): string => {
  const descriptionBody = sections.map((item) => {
    const { name, props, type: typeSection } = item;

    const sectionDescription = item?.description;

    const noteSection = item?.note
      ? `<em>${item?.note}</em><br></details>`
      : '';

    if (isNil(props) || isEmpty(props)) {
      return `${initialValue({
        section: name,
        sectionDescription,
        type: typeSection
      })}${noteSection}`;
    }

    const formattedProps = props.reduce((accumulator, currentValue, index) => {
      const key = Object.keys(currentValue)[0];
      const { description, type } = currentValue[key];

      if (!equals(index, props.length - 1)) {
        return `${accumulator} ${getBodyDescription({
          description,
          key,
          type
        })}`;
      }

      return `${accumulator} ${getBodyDescription({
        description,
        key,
        type
      })} <br>${noteSection}`;
    }, initialValue({ section: name, sectionDescription, type: typeSection }));

    return formattedProps as string;
  });

  const result = descriptionBody.reduce(
    (accumulateur, currentValue) => `${accumulateur}${currentValue}`
  );

  return result;
};

const propsAxisX = [
  {
    xAxisTickFormat: {
      description:
        'string of the formatted date for the tick text, reference to the format of dayjs',
      type: 'string'
    }
  }
];

const propsAxisY = [
  { display: { description: 'display or not the axis', type: 'boolean' } },
  {
    displayUnit: {
      description: 'display or not the unit of the axis',
      type: 'boolean'
    }
  }
];

Playground.argTypes = {
  anchorPoint: {
    control: 'object',
    defaultValue: {
      areaRegularLinesAnchorPoint: {
        display: true
      },
      areaStackedLinesAnchorPoint: {
        display: true
      }
    },
    description: getDescription({
      sections: [
        {
          description: 'Anchor point for the shape line [areaRegularLines]',
          name: 'areaRegularLinesAnchorPoint',
          note: 'coming soon',
          props: [
            {
              display: {
                description: 'display or not the anchor point',
                type: 'boolean'
              }
            }
          ],
          type: 'object'
        },
        {
          description: 'Anchor point for the shape line [areaStackedLines ]',
          name: 'areaStackedLinesAnchorPoint',
          note: 'coming soon',
          props: [
            {
              display: {
                description: 'display or not the anchor point',
                type: 'boolean'
              }
            }
          ],
          type: 'object'
        }
      ]
    }),
    table: {
      category: 'Graph interaction',
      type: {
        detail:
          'displays the timing, circle, vertical and horizontal line for each point of the corresponding graph (line) according to the interaction of the mouse with the graph',
        summary: 'object'
      }
    }
  },
  axis: {
    axisX: {
      xAxisTickFormat: { control: 'text' }
    },
    axisYLeft: {
      display: { control: 'boolean' },
      displayUnit: { control: 'boolean' }
    },
    axisYRight: {
      displayUnit: { control: 'boolean' }
    },
    control: 'object',
    defaultValue: {
      axisYLeft: { displayUnit: true },
      axisYRight: { display: true, displayUnit: true }
    },

    description: getDescription({
      sections: [
        {
          description: 'axis bottom ',
          name: 'axisX',
          note: getNote({
            componentName: 'AxisBottom',
            link: 'https://airbnb.io/visx/docs/axis#AxisBottom'
          }),
          props: propsAxisX,
          type: 'object'
        },
        {
          description: 'axis left ',
          name: 'axisYLeft',
          note: getNote({
            componentName: 'AxisLeft',
            link: 'https://airbnb.io/visx/docs/axis#AxisLeft'
          }),
          props: propsAxisY,
          type: 'object'
        },
        {
          description: 'axis right ',
          name: 'axisYRight',
          note: getNote({
            componentName: 'AxisRight',
            link: 'https://airbnb.io/visx/docs/axis#AxisRight'
          }),
          props: propsAxisY,
          type: 'object'
        }
      ]
    }),
    table: {
      category: 'Graph component',
      type: { detail: 'control the axis of the graph', summary: 'object' }
    }
  },
  baseUrl: {
    control: {
      type: 'text'
    },
    defaultValue:
      'http://localhost:3000/centreon/api/latest/monitoring/hosts/151/services/1160/metrics/performance',
    description: 'base url to get graph data',
    name: 'baseUrl',
    table: {
      category: 'Graph data',
      type: { summary: 'string' }
    },
    type: { name: 'string', required: true }
  },
  end: {
    control: 'date',
    defaultValue: Date.now(),
    description: 'the end of the interval of time to get graph data',
    table: {
      category: 'Graph data',
      type: { detail: 'the end of the interval', summary: 'date' }
    },
    type: { required: true }
  },
  grids: {
    control: 'object',
    defaultValue: {
      column: {
        stroke: '#eaf0f6'
      },
      row: {
        stroke: '#eaf0f6'
      }
    },
    description: getDescription({
      sections: [
        {
          description: 'Grid columns',
          name: 'row',
          note: getNote({
            componentName: 'GridRows',
            link: 'https://airbnb.io/visx/docs/grid#GridRows'
          }),
          props: [],
          type: 'object'
        },
        {
          description: 'Grid rows',
          name: 'column',
          note: getNote({
            componentName: 'GridColumns',
            link: 'https://airbnb.io/visx/docs/grid#GridColumns'
          }),
          props: [],
          type: 'object'
        }
      ]
    }),
    table: {
      category: 'Graph component',
      type: { detail: 'control the grid lines of the graph', summary: 'object' }
    }
  },
  height: {
    control: 'number',
    defaultValue: 500,
    description: 'the height of the graph',
    table: {
      category: 'Sizes',
      type: { summary: 'number' }
    }
  },
  shapeLines: {
    control: 'object',
    defaultValue: {
      areaRegularLines: {
        display: true,
        shapeAreaClosed: { stroke: '#2B28D7' },
        shapeLinePath: { fill: 'transparent' }
      },
      areaStackedLines: {
        display: true
      }
    },
    description: getDescription({
      sections: [
        {
          description:
            'representing 2 areas (according to the data), filled area (areaClosed) and line path (linePath)',
          name: 'areaRegularLines',
          note: 'For more information plz visit [click here](https://airbnb.io/visx/docs/shape) for AreaClosed and LinePath components',
          props: [
            {
              display: {
                description: 'display or not the area regular lines',
                type: 'boolean'
              }
            },
            {
              shapeAreaClosed: {
                description:
                  'represents area filled in the graph. you can use the areaClosed props from visx to override the default props of this component',
                type: 'object'
              }
            },
            {
              shapeLinePath: {
                description:
                  'represents the single line in the graph. you can use the LinePath props from visx to override the default props of this component',
                type: 'object'
              }
            }
          ],
          type: 'object'
        },
        {
          description: 'representing the area stack in the graph',
          name: 'areaStackedLines',
          note: getNote({
            componentName: 'AreaStack',
            link: 'https://airbnb.io/visx/docs/shape#AreaStack'
          }),
          props: [
            {
              display: {
                description: 'display or not the area',
                type: 'boolean'
              }
            }
          ],
          type: 'object'
        }
      ]
    }),
    table: {
      category: 'Graph component',
      type: { detail: 'control the lines of the graph', summary: 'object' }
    }
  },
  start: {
    control: 'date',
    defaultValue: dayjs(Date.now()).subtract(24, 'hour').toDate().getTime(),
    description: 'the beginning of the interval of time to get graph data',
    table: {
      category: 'Graph data',
      type: { detail: 'the beginning of the interval', summary: 'date' }
    },
    type: { required: true }
  },
  width: {
    control: 'number',
    description: 'the width of the graph',
    table: {
      category: 'Sizes',
      type: { summary: 'number' }
    }
  },
  zoomPreview: {
    control: 'object',
    defaultValue: {
      display: true,
      y: 0
    },
    description: getDescription({
      sections: [
        {
          description: 'control zoomPreview ',
          name: '',
          note: getNote({
            componentName: 'Bar',
            link: 'https://airbnb.io/visx/docs/shape#Bar'
          }),
          props: [
            {
              display: {
                description: 'enable or not the zoomPreview',
                type: 'boolean'
              }
            }
          ],
          type: 'object'
        }
      ]
    }),
    table: {
      category: 'Graph interaction',
      type: {
        summary: 'apply zoom to a specific zoon'
      }
    }
  }
};

Playground.args = {};
