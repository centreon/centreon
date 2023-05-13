import dayjs from 'dayjs';
import { equals, isEmpty, isNil } from 'ramda';

interface InitialValue {
  section: string;
  sectionDescription?: string;
  type: string;
}

const getInitialValue = ({ section, type }: InitialValue): string => `
  <details>
  <summary>${section} ${getCustomText(type)}</summary>
  >`;

interface Prop {
  description: string;
  type: string;
}

interface Section {
  description?: string;
  name: string;
  note?: string;
  props: Array<Record<string, Prop>>;
  type: string;
}

interface Description {
  sections: Array<Section>;
}

export const defaultBaseUrl =
  'http://localhost:3000/centreon/api/latest/monitoring/hosts/151/services/1160/metrics/performance';

export const defaultStart = new Date(
  dayjs(Date.now()).subtract(24, 'hour').toDate().getTime()
).toISOString();

export const defaultEnd = new Date(Date.now()).toISOString();
export const defaultLast7days = new Date(
  dayjs(Date.now()).subtract(7, 'day').toDate().getTime()
).toISOString();

export const defaultLastMonth = new Date(
  dayjs(Date.now()).subtract(31, 'day').toDate().getTime()
).toISOString();

export const zoomPreviewDate = '2023-06-01';
export const lastDayForwardDate = '2023-06-07';

export const getCustomText = (text: string): string =>
  `<span style="color:#1EA7FD;fontSize:12px">(${text})</span>`;

export const getBodyDescription = ({ key, description, type }): string =>
  `<strong>${key}</strong> : ${description} ${getCustomText(type)} <br>`;

export const getDescription = ({ sections }: Description): string => {
  const descriptionBody = sections.map((item) => {
    const { name, props, type: typeSection } = item;

    if (isNil(props) || isEmpty(props)) {
      return `${getInitialValue({
        section: name,
        type: typeSection
      })}<br></details>`;
    }

    const formattedProps = props.reduce((accumulator, currentValue, index) => {
      const key = Object.keys(currentValue)[0];
      const { description, type } = currentValue[key];
      const body = `${accumulator} ${getBodyDescription({
        description,
        key,
        type
      })}`;

      if (!equals(index, props.length - 1)) {
        return body;
      }

      return `${body}</details>`;
    }, getInitialValue({ section: name, type: typeSection }));

    return formattedProps as string;
  });

  const result = descriptionBody.reduce(
    (accumulate, currentValue) => `${accumulate}${currentValue}`
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

export const argTypes = {
  anchorPoint: {
    control: 'object',
    description: getDescription({
      sections: [
        {
          name: 'areaRegularLinesAnchorPoint',
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
          name: 'areaStackedLinesAnchorPoint',
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
    description: getDescription({
      sections: [
        {
          name: 'axisX',
          props: propsAxisX,
          type: 'object'
        },
        {
          name: 'axisYLeft',
          props: propsAxisY,
          type: 'object'
        },
        {
          name: 'axisYRight',
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
  data: {
    control: 'object',
    description: 'the data of the graph'
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
    description: getDescription({
      sections: [
        {
          name: 'areaRegularLines',
          props: [
            {
              display: {
                description: 'display or not the area regular lines',
                type: 'boolean'
              }
            }
          ],
          type: 'object'
        },
        {
          name: 'areaStackedLines',
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
    description: getDescription({
      sections: [
        {
          name: '',
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
  anchorPoint: {
    areaRegularLinesAnchorPoint: {
      display: true
    },
    areaStackedLinesAnchorPoint: {
      display: true
    },
    areaThresholdLines: {
      display: true
    }
  },
  axis: {
    // axisX: { xAxisTickFormat: undefined },
    axisYLeft: { displayUnit: true },
    axisYRight: { display: true, displayUnit: true }
  },
  end: defaultEnd,
  height: 500,
  shapeLines: {
    areaRegularLines: {
      display: true
    },
    areaStackedLines: {
      display: true
    },
    areaThresholdLines: {
      display: true
    }
  },
  start: defaultStart,
  zoomPreview: {
    enable: true
  }
};
