export const successThresholds = {
  critical: [
    {
      label: 'Critical',
      value: 1.5
    }
  ],
  enabled: true,
  warning: [
    {
      label: 'Warning',
      value: 0.5
    }
  ]
};

export const warningThresholds = {
  critical: [
    {
      label: 'Critical',
      value: 1.5
    }
  ],
  enabled: true,
  warning: [
    {
      label: 'Warning',
      value: 0.4
    }
  ]
};

export const criticalThresholds = {
  critical: [
    {
      label: 'Critical',
      value: 0.3
    }
  ],
  enabled: true,
  warning: [
    {
      label: 'Warning',
      value: 0.2
    }
  ]
};

export const rangedThresholds = {
  critical: [
    {
      label: 'Critical',
      value: 0.55
    },
    {
      label: 'Critical',
      value: 0.65
    }
  ],
  enabled: true,
  warning: [
    {
      label: 'Warning',
      value: 0.13
    },
    {
      label: 'Warning',
      value: 0.5
    }
  ]
};

const number = /-?\d*\.?\d+(e-?\d+)?/g;

const geometryAttributes = [
  'cx',
  'cy',
  'dx',
  'dy',
  'height',
  'points',
  'r',
  'transform',
  'width',
  'x',
  'x1',
  'x2',
  'y',
  'y1',
  'y2'
];

// Chart geometry depends on measured text sizes, which differ between operating systems,
// and so do the axis tick and grid line counts: the explicit assertions cover them.
export const getNormalizedChart = (): Element => {
  const chart = document
    .querySelector('svg[aria-label="graph"]')
    ?.cloneNode(true) as Element;

  chart
    .querySelectorAll('.visx-axis, .visx-rows, .visx-columns')
    .forEach((element) => {
      element.remove();
    });

  [chart, ...chart.querySelectorAll('*')].forEach((element) => {
    const path = element.getAttribute('d');

    // A path keeps its command sequence (curve type, gaps), not one entry per point.
    if (path !== null) {
      element.setAttribute(
        'd',
        path
          .replace(number, '')
          .replace(/[^a-zA-Z]/g, '')
          .replace(/(.)\1+/g, '$1')
      );
    }

    // useId follows the render order, which changes when a single test runs.
    ['id', 'fill'].forEach((name) => {
      const value = element.getAttribute(name);

      if (value?.includes('time-area-')) {
        element.setAttribute(
          name,
          value.replace(/time-area-\w+-/, 'time-area-id-')
        );
      }
    });

    geometryAttributes.forEach((name) => {
      const value = element.getAttribute(name);

      if (value !== null) {
        element.setAttribute(name, value.replace(number, 'n'));
      }
    });
  });

  return chart;
};
