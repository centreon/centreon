import dayjs from 'dayjs';
import { equals, isEmpty, isNil } from 'ramda';

interface InitialValue {
  section: string;
  sectionDescription?: string;
  type?: string;
}

const getInitialValue = ({ section, type }: InitialValue): string => `
  <details>
  <summary>${section}${type && getCustomText(type)}</summary>
  >`;

interface Prop {
  description: string;
  type?: string;
}

interface Section {
  description?: string;
  name: string;
  props: Array<Record<string, Prop>>;
  type?: string;
}

interface Description {
  sections: Array<Section>;
}

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

export const getBodyDescription = ({ key, description, type }): string => {
  const body = !type
    ? `${description} <br>`
    : `${description} ${getCustomText(type)} <br>`;
  if (!key) {
    return body;
  }

  return `<strong>${key}</strong> : ${body}`;
};

export const getDescription = ({ sections }: Description): string => {
  const descriptionBody = sections.map((item) => {
    const { name, props } = item;

    if (isNil(props) || isEmpty(props)) {
      return `${getInitialValue({
        section: name,
        type: item?.type
      })}<br></details>`;
    }

    const formattedProps = props.reduce(
      (accumulator, currentValue, index) => {
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
      },
      getInitialValue({ section: name, type: item?.type })
    );

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
    }
  },

  displayAnchor: {
    control: 'object',
    description: getDescription({
      sections: [
        {
          name: '',
          props: [
            {
              displayGuidingLines: {
                description: 'displays the guiding lines',
                type: 'boolean'
              }
            },
            {
              displayTooltipsGuidingLines: {
                description:
                  'displays the tooltips (labels) on the axis bottom/left/right',
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
        summary:
          'displays or not the guiding lines and the dots for each point of the corresponding graph (line) according to the interaction of the mouse with the graph'
      }
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
              '': {
                description: getDescription({
                  sections: [
                    {
                      name: 'object',
                      props: [
                        {
                          type: {
                            description:
                              'should be variation to render threshold of type variation'
                          }
                        },
                        {
                          factors: {
                            description:
                              'an object of currentFactorMultiplication (the variant to calculate the envelope variation) and the simulatedFactorMultiplication -number-) (the simulated factor of envelope variation -number-) useful for envelopVariation formula',
                            type: 'required'
                          }
                        },
                        {
                          getCountDisplayedCircles: {
                            description:
                              'callback return the counted circles out of the envelope variation depends on mouse position relative to time value (t)',
                            type: '(data:number) => void'
                          }
                        }
                      ],
                      type: 'render threshold of type variation'
                    },
                    {
                      name: 'object',
                      props: [
                        {
                          type: {
                            description:
                              'should be variation to render threshold of type pattern'
                          }
                        },
                        {
                          data: {
                            description:
                              'array of graph data ,showing threshold with Patter lines',
                            type: 'required'
                          }
                        }
                      ],
                      type: 'render threshold of type pattern'
                    },
                    {
                      name: 'object',
                      props: [
                        {
                          type: {
                            description:
                              'should be variation to render threshold of type basic'
                          }
                        }
                      ],
                      type: 'render threshold of type basic'
                    }
                  ]
                }),
                type: 'Array of types data (basic | variation | pattern) to render thresholds (can mix multiple)'
              }
            }
          ],
          type: 'array'
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
  end: defaultEnd,
  height: 500,
  loading: false,
  start: defaultStart
};
