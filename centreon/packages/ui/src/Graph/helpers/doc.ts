import dayjs from 'dayjs';
import { equals, isEmpty, isNil } from 'ramda';
import { boolean } from 'yup';

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

export const getCustomText = (text: string): string =>
  `<span style="color:#1EA7FD;fontSize:12px">(${text})</span>`;

export const getBodyDescription = ({ key, description, type }): string =>
  `<strong>${key}</strong> : ${description} ${getCustomText(type)} <br>`;

export const getNote = ({ componentName, link }): string =>
  `You can use ${componentName} props to override the default props of this component [click here](${link})`;

export const getDescription = ({ sections }: Description): string => {
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

export const propsAxisX = [
  {
    xAxisTickFormat: {
      description:
        'string of the formatted date for the tick text, reference to the format of dayjs',
      type: 'string'
    }
  }
];

export const propsAxisY = [
  { display: { description: 'display or not the axis', type: 'boolean' } },
  {
    displayUnit: {
      description: 'display or not the unit of the axis',
      type: 'boolean'
    }
  }
];

const defaultBaseUrl =
  'http://localhost:3000/centreon/api/latest/monitoring/hosts/151/services/1160/metrics/performance';
const defaultStart = new Date(
  dayjs(Date.now()).subtract(24, 'hour').toDate().getTime()
).toISOString();

const defaultEnd = new Date(Date.now()).toISOString();

const enum Test {
  'string',
  'number'
}

export const argTypes = {
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
    description: 'base url to get graph data',
    name: 'baseUrl',
    table: {
      category: 'Graph data',
      defaultValue: {
        summary: defaultBaseUrl
      },
      type: { required: true, summary: 'string' }
    }
    // type: { name: 'string', required: true }
  },
  end: {
    control: 'text',
    description: 'the end of the interval of time to get graph data',
    table: {
      category: 'Graph data',
      type: {
        detail: 'the end of the interval',
        summary: 'ISOString (required*)'
      }
    }
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
    control: 'text',
    description: 'the beginning of the interval of time to get graph data',
    name: 'start',
    table: {
      category: 'Graph data',
      type: {
        detail: 'the beginning of the interval',
        summary: 'ISOString (required*)'
      }
    }
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

export const args = {
  baseUrl: defaultBaseUrl,
  end: defaultEnd,
  height: 500,
  start: defaultStart
};
