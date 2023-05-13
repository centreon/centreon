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
  {
    displayUnit: {
      description: 'display or not the unit of the axis',
      type: 'boolean'
    }
  }
];

export const argTypes = {
  annotationEvent: {
    control: 'object',
    description: getDescription({
      sections: [
        {
          name: '',
          props: [
            {
              data: {
                description:
                  'if the data is provided , events (comments,downtime,acknowledgement) will be displayed',
                type: 'array of events'
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
        summary: 'display events'
      }
    }
  },
  axis: {
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
    description: getDescription({
      sections: [
        {
          name: '',
          props: [
            {
              global: {
                description: 'global data of graph like title ...',
                type: 'object'
              }
            },
            {
              metrics: {
                description: '',
                type: 'array of object (ds_data)'
              }
            },
            {
              times: {
                description: '',
                type: 'array of dates -iso string-'
              }
            }
          ],
          type: 'object'
        }
      ]
    }),
    table: {
      category: 'Graph data'
    },
    type: {
      required: true
    }
  },
  displayAnchor: {
    control: 'boolean',
    description:
      'displays or not the timing, circle, vertical and horizontal line for each point of the corresponding graph (line) according to the interaction of the mouse with the graph',
    table: {
      category: 'Graph interaction',
      type: { summary: 'boolean' }
    }
  },
  end: {
    control: 'text',
    description: 'the end of the interval',
    table: {
      category: 'Graph data',
      type: {
        detail: 'the end of the interval',
        summary: 'ISOString'
      }
    },
    type: {
      required: true
    }
  },
  header: {
    control: 'object',
    description: getDescription({
      sections: [
        {
          name: '',
          props: [
            {
              displayTitle: {
                description: 'display or not the title of the graph',
                type: 'boolean'
              }
            },
            {
              extraComponent: {
                description: 'extra component to display on header graph',
                type: 'React node'
              }
            }
          ],
          type: 'object'
        }
      ]
    }),
    table: {
      category: 'Graph component',
      type: {
        summary: 'control header of the graph'
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
  legend: {
    control: 'object',
    description: getDescription({
      sections: [
        {
          name: '',
          props: [
            {
              display: {
                description: 'display or not the legend',
                type: 'boolean'
              }
            },
            {
              renderExtraComponent: {
                description: 'extra component to render with legend ',
                type: 'React node'
              }
            }
          ],
          type: 'object'
        }
      ]
    }),
    table: {
      category: 'Graph component',
      type: {
        summary: 'control applying zoom to a specific zoon'
      }
    }
  },
  loading: {
    control: 'boolean',
    description: 'the loading indicator ',
    table: {
      category: 'Graph data',
      type: { summary: 'boolean' }
    },
    type: {
      required: true
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
                description: 'display or not the area stacked lines',
                type: 'boolean'
              }
            }
          ],
          type: 'object'
        },
        {
          name: 'areaThresholdLines',
          props: [
            {
              display: {
                description:
                  'display or not the area threshold lines , if not the component will display the corresponding graph according to the data (regular area lines)',
                type: 'boolean'
              }
            },
            {
              displayCircles: {
                description: 'display or not the circles',
                type: 'boolean'
              }
            },
            {
              factors: {
                description: getDescription({
                  sections: [
                    {
                      name: 'details',
                      props: [
                        {
                          currentFactorMultiplication: {
                            description:
                              'the variant to calculate the envelope variation',
                            type: 'number'
                          }
                        },
                        {
                          simulatedFactorMultiplication: {
                            description:
                              'the simulated factor of envelope variation',
                            type: 'number'
                          }
                        }
                      ],
                      type: 'object'
                    }
                  ]
                }),
                type: 'if the object is provided, the envelope variation of the graph is displayed according to the factors provided'
              }
            },
            {
              getCountDisplayedCircles: {
                description:
                  'callback return the counted circles out of the envelope variation depends on mouse position relative to time value (t)',
                type: '() => void'
              }
            },
            {
              dataExclusionPeriods: {
                description:
                  'array of graph data ,showing threshold with Patter lines',
                type: 'if the object is provided , the thresholds with pattern lines will be displayed'
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
    description: 'the beginning of the interval of time ',
    name: 'start',
    table: {
      category: 'Graph data',
      type: {
        detail: 'the beginning of the interval',
        summary: 'ISOString (required*)'
      }
    },
    type: {
      required: true
    }
  },
  timeShiftZones: {
    control: 'object',
    description: getDescription({
      sections: [
        {
          name: '',
          props: [
            {
              enable: {
                description: 'enable or not the action drag',
                type: 'boolean'
              }
            },
            {
              getInterval: {
                description:
                  'callback return the new interval of the graph (end, start) after the action drag',
                type: '({end,start}) => void'
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
        summary: 'control applying zoom to a specific zoon'
      }
    }
  },
  tooltip: {
    control: 'object',
    description: getDescription({
      sections: [
        {
          name: '',
          props: [
            {
              renderComponent: {
                description:
                  'render function with given props of tooltip data (date of the tooltip position depend on the mouse click , hideTooltip callback to hide tooltip , boolean of is tooltip opened)',
                type: '({data,hideTooltip,tooltipOpen}) => void , if the component provided the tooltip displays'
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
        summary: 'control graph tooltip'
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
              enable: {
                description: 'enable or not the zoomPreview',
                type: 'boolean'
              }
            },
            {
              getInterval: {
                description:
                  'callback return the new interval of the graph (end, start) after applying zoom',
                type: '({end,start}) => void'
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
        summary: 'control applying zoom to a specific zoon'
      }
    }
  }
};

export const args = {
  axis: {
    axisYLeft: { displayUnit: true },
    axisYRight: { displayUnit: true }
  },
  displayAnchor: true,
  end: defaultEnd,
  height: 500,
  loading: false,
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
