import { LineChartData } from '../models';

import * as timeSeries from '.';

type TestCase = [number | null, string, 1000 | 1024, string | null];

describe('timeSeries', () => {
  const graphData: LineChartData = {
    global: {},
    metrics: [
      {
        average_value: 1,
        data: [0, 1],
        ds_data: {
          ds_color_area: 'transparent',
          ds_color_line: 'black',
          ds_filled: false,
          ds_invert: null,
          ds_legend: 'Round-Trip-Time Average',
          ds_order: null,
          ds_stack: null,
          ds_transparency: 80
        },
        legend: 'Round-Trip-Time Average (ms)',
        maximum_value: 1.5,
        metric: 'rta',
        metric_id: 1,
        minimum_value: 0.5,
        unit: 'ms'
      },
      {
        average_value: 1,
        data: [0.5, 3],
        ds_data: {
          ds_color_area: 'blue',
          ds_color_line: 'blue',
          ds_filled: true,
          ds_invert: null,
          ds_legend: 'Time',
          ds_order: null,
          ds_stack: null,
          ds_transparency: 80
        },
        legend: 'Time (ms)',
        maximum_value: 1.5,
        metric: 'time',
        metric_id: 2,
        minimum_value: 0.5,
        unit: 'ms'
      },
      {
        average_value: 1,
        data: [6, 4],
        ds_data: {
          ds_color_area: 'red',
          ds_color_line: 'red',
          ds_filled: true,
          ds_invert: null,
          ds_legend: 'Average duration',
          ds_order: '2',
          ds_stack: '1',
          ds_transparency: 80
        },
        legend: 'Average duration (ms)',
        maximum_value: 1.5,
        metric: 'avgDuration',
        metric_id: 3,
        minimum_value: 0.5,
        unit: 'ms'
      },
      {
        average_value: 1,
        data: [12, 25],
        ds_data: {
          ds_color_area: 'yellow',
          ds_color_line: 'yellow',
          ds_filled: true,
          ds_invert: '1',
          ds_legend: 'Duration',
          ds_order: '1',
          ds_stack: '1',
          ds_transparency: 80
        },
        legend: 'Duration (ms)',
        maximum_value: 1.5,
        metric: 'duration',
        metric_id: 4,
        minimum_value: 0.5,
        unit: 'ms'
      },
      {
        average_value: 1,
        data: [0, 1],
        ds_data: {
          ds_color_area: 'yellow',
          ds_color_line: 'yellow',
          ds_filled: true,
          ds_invert: null,
          ds_legend: 'Packet Loss',
          ds_order: null,
          ds_stack: null,
          ds_transparency: 80
        },
        legend: 'Packet Loss (%)',
        maximum_value: 1.5,
        metric: 'packet_loss',
        metric_id: 5,
        minimum_value: 0.5,
        unit: '%'
      }
    ],
    times: ['2020-11-05T10:35:00Z', '2020-11-05T10:40:00Z']
  };

  describe('getTimeSeries', () => {
    it('returns the time series for the given graph data', () => {
      expect(timeSeries.getTimeSeries(graphData)).toEqual([
        {
          1: 0,
          2: 0.5,
          3: 6,
          4: 12,
          5: 0,
          timeTick: '2020-11-05T10:35:00Z'
        },
        {
          1: 1,
          2: 3,
          3: 4,
          4: 25,
          5: 1,
          timeTick: '2020-11-05T10:40:00Z'
        }
      ]);
    });

    it('filters metric values below the given lower-limit value', () => {
      const graphDataWithLowerLimit = {
        ...graphData,
        global: {
          'lower-limit': 0.4
        }
      };

      expect(timeSeries.getTimeSeries(graphDataWithLowerLimit)).toEqual([
        {
          2: 0.5,
          3: 6,
          4: 12,
          timeTick: '2020-11-05T10:35:00Z'
        },
        {
          1: 1,
          2: 3,
          3: 4,
          4: 25,
          5: 1,
          timeTick: '2020-11-05T10:40:00Z'
        }
      ]);
    });
  });

  describe('getLineData', () => {
    it('returns the line information for the given graph data', () => {
      expect(timeSeries.getLineData(graphData)).toEqual([
        {
          areaColor: 'transparent',
          average_value: 1,
          color: 'black',
          display: true,
          filled: false,
          highlight: undefined,
          invert: null,
          legend: 'Round-Trip-Time Average',
          lineColor: 'black',
          maximum_value: 1.5,
          metric: 'rta',
          metric_id: 1,
          minimum_value: 0.5,
          name: 'Round-Trip-Time Average (ms)',
          stackOrder: null,
          transparency: 80,
          unit: 'ms'
        },
        {
          areaColor: 'blue',
          average_value: 1,
          color: 'blue',
          display: true,
          filled: true,
          highlight: undefined,
          invert: null,
          legend: 'Time',
          lineColor: 'blue',
          maximum_value: 1.5,
          metric: 'time',
          metric_id: 2,
          minimum_value: 0.5,
          name: 'Time (ms)',
          stackOrder: null,
          transparency: 80,
          unit: 'ms'
        },
        {
          areaColor: 'red',
          average_value: 1,
          color: 'red',
          display: true,
          filled: true,
          highlight: undefined,
          invert: null,
          legend: 'Average duration',
          lineColor: 'red',
          maximum_value: 1.5,
          metric: 'avgDuration',
          metric_id: 3,
          minimum_value: 0.5,
          name: 'Average duration (ms)',
          stackOrder: 2,
          transparency: 80,
          unit: 'ms'
        },
        {
          areaColor: 'yellow',
          average_value: 1,
          color: 'yellow',
          display: true,
          filled: true,
          highlight: undefined,
          invert: '1',
          legend: 'Duration',
          lineColor: 'yellow',
          maximum_value: 1.5,
          metric: 'duration',
          metric_id: 4,
          minimum_value: 0.5,
          name: 'Duration (ms)',
          stackOrder: 1,
          transparency: 80,
          unit: 'ms'
        },
        {
          areaColor: 'yellow',
          average_value: 1,
          color: 'yellow',
          display: true,
          filled: true,
          highlight: undefined,
          invert: null,
          legend: 'Packet Loss',
          lineColor: 'yellow',
          maximum_value: 1.5,
          metric: 'packet_loss',
          metric_id: 5,
          minimum_value: 0.5,
          name: 'Packet Loss (%)',
          stackOrder: null,
          transparency: 80,
          unit: '%'
        }
      ]);
    });
  });

  describe('getMetrics', () => {
    it('returns the metrics for the given time value', () => {
      expect(
        timeSeries.getMetrics({
          rta: 1,
          time: 0,
          timeTick: '2020-11-05T10:40:00Z'
        })
      ).toEqual(['rta', 'time']);
    });
  });

  describe('getMetricValuesForUnit', () => {
    it('returns the values in the given time series corresponding to the given line unit', () => {
      const series = timeSeries.getTimeSeries(graphData);
      const lines = timeSeries.getLineData(graphData);
      const unit = 'ms';

      expect(
        timeSeries.getMetricValuesForUnit({ lines, timeSeries: series, unit })
      ).toEqual([0, 1, 0.5, 3, 6, 4, 12, 25]);
    });
  });

  describe('getUnits', () => {
    it('returns the units for the given lines', () => {
      const lines = timeSeries.getLineData(graphData);

      expect(timeSeries.getUnits(lines)).toEqual(['ms', '%']);
    });
  });

  describe('getDates', () => {
    it('teruns the dates for the given time series', () => {
      const series = timeSeries.getTimeSeries(graphData);

      expect(timeSeries.getDates(series)).toEqual([
        new Date('2020-11-05T10:35:00.000Z'),
        new Date('2020-11-05T10:40:00.000Z')
      ]);
    });
  });

  describe('getLineForMetric', () => {
    it('returns the line corresponding to the given metrics', () => {
      const lines = timeSeries.getLineData(graphData);

      expect(timeSeries.getLineForMetric({ lines, metric_id: 1 })).toEqual({
        areaColor: 'transparent',
        average_value: 1,
        color: 'black',
        display: true,
        filled: false,
        highlight: undefined,
        invert: null,
        legend: 'Round-Trip-Time Average',
        lineColor: 'black',
        maximum_value: 1.5,
        metric: 'rta',
        metric_id: 1,
        minimum_value: 0.5,
        name: 'Round-Trip-Time Average (ms)',
        stackOrder: null,
        transparency: 80,
        unit: 'ms'
      });
    });
  });

  describe('getMetricValuesForLines', () => {
    it('returns the metric values for the given lines within the given time series', () => {
      const lines = timeSeries.getLineData(graphData);
      const series = timeSeries.getTimeSeries(graphData);

      expect(
        timeSeries.getMetricValuesForLines({ lines, timeSeries: series })
      ).toEqual([0, 1, 0.5, 3, 6, 4, 12, 25, 0, 1]);
    });
  });

  describe(timeSeries.formatMetricValue, () => {
    const cases: Array<TestCase> = [
      [218857269, '', 1000, '218.86m'],
      [218857269, '', 1024, '208.72 M'],
      [0.12232323445, '', 1000, '0.12'],
      [1024, 'B', 1000, '1 KB'],
      [1024, 'B', 1024, '1 KB'],
      [null, 'B', 1024, null]
    ];

    it.each(cases)(
      'formats the given value to a human readable form according to the given unit and base',
      (value, unit, base, formattedResult) => {
        expect(timeSeries.formatMetricValue({ base, unit, value })).toEqual(
          formattedResult
        );
      }
    );
  });

  describe('getSortedStackedLines', () => {
    it('returns stacked lines sorted by their own order for the given lines', () => {
      const lines = timeSeries.getLineData(graphData);

      expect(timeSeries.getSortedStackedLines(lines)).toEqual([
        {
          areaColor: 'yellow',
          average_value: 1,
          color: 'yellow',
          display: true,
          filled: true,
          highlight: undefined,
          invert: '1',
          legend: 'Duration',
          lineColor: 'yellow',
          maximum_value: 1.5,
          metric: 'duration',
          metric_id: 4,
          minimum_value: 0.5,
          name: 'Duration (ms)',
          stackOrder: 1,
          transparency: 80,
          unit: 'ms'
        },
        {
          areaColor: 'red',
          average_value: 1,
          color: 'red',
          display: true,
          filled: true,
          highlight: undefined,
          invert: null,
          legend: 'Average duration',
          lineColor: 'red',
          maximum_value: 1.5,
          metric: 'avgDuration',
          metric_id: 3,
          minimum_value: 0.5,
          name: 'Average duration (ms)',
          stackOrder: 2,
          transparency: 80,
          unit: 'ms'
        }
      ]);
    });
  });

  describe('getStackedMetricValues', () => {
    it('returns stacked metrics values for the given lines and the given time series', () => {
      const lines = timeSeries.getLineData(graphData);
      const series = timeSeries.getTimeSeries(graphData);

      expect(
        timeSeries.getStackedMetricValues({
          lines: timeSeries.getSortedStackedLines(lines),
          timeSeries: series
        })
      ).toEqual([18, 29]);
    });
  });

  describe('getTimeSeriesForLines', () => {
    it('returns the specific time series for the given lines and the fiven time series', () => {
      const lines = timeSeries.getLineData(graphData);
      const series = timeSeries.getTimeSeries(graphData);

      expect(
        timeSeries.getTimeSeriesForLines({
          lines: timeSeries.getSortedStackedLines(lines),
          timeSeries: series
        })
      ).toEqual([
        {
          3: 6,
          4: 12,
          timeTick: '2020-11-05T10:35:00Z'
        },
        {
          3: 4,
          4: 25,
          timeTick: '2020-11-05T10:40:00Z'
        }
      ]);
    });
  });

  describe('getInvertedStackedLines', () => {
    it('returns inverted and stacked lines for the given lines', () => {
      const lines = timeSeries.getLineData(graphData);

      expect(timeSeries.getInvertedStackedLines(lines)).toEqual([
        {
          areaColor: 'yellow',
          average_value: 1,
          color: 'yellow',
          display: true,
          filled: true,
          highlight: undefined,
          invert: '1',
          legend: 'Duration',
          lineColor: 'yellow',
          maximum_value: 1.5,
          metric: 'duration',
          metric_id: 4,
          minimum_value: 0.5,
          name: 'Duration (ms)',
          stackOrder: 1,
          transparency: 80,
          unit: 'ms'
        }
      ]);
    });
  });

  describe('getNotInvertedStackedLines', () => {
    it('returns not inverted and stacked lines for the given lines', () => {
      const lines = timeSeries.getLineData(graphData);

      expect(timeSeries.getNotInvertedStackedLines(lines)).toEqual([
        {
          areaColor: 'red',
          average_value: 1,
          color: 'red',
          display: true,
          filled: true,
          highlight: undefined,
          invert: null,
          legend: 'Average duration',
          lineColor: 'red',
          maximum_value: 1.5,
          metric: 'avgDuration',
          metric_id: 3,
          minimum_value: 0.5,
          name: 'Average duration (ms)',
          stackOrder: 2,
          transparency: 80,
          unit: 'ms'
        }
      ]);
    });
  });

  describe('hasUnitStackedLines', () => {
    it('returns true if the given unit contains stacked lines following the given lines, false otherwise', () => {
      const lines = timeSeries.getLineData(graphData);

      expect(timeSeries.hasUnitStackedLines({ lines, unit: 'ms' })).toEqual(
        true
      );

      expect(timeSeries.hasUnitStackedLines({ lines, unit: '%' })).toEqual(
        false
      );
    });
  });
});

describe('Format value with unit', () => {
  const units = [
    'B',
    'bytes',
    'bytespersecond',
    'B/s',
    'B/sec',
    'o',
    'octets',
    'b/s',
    'b',
    'ms',
    '%',
    ''
  ];

  const getExpectedResult = (unit): string => {
    if (unit === '') {
      return '324.23m';
    }

    return `309.21 M${unit}`;
  };

  const humanReadableTestCases = units.map((unit) => {
    if (unit === '%') {
      return {
        expectedResult: '45.56%',
        unit,
        value: 45.55678
      };
    }

    if (unit === 'ms') {
      return {
        expectedResult: '34.23 seconds',
        unit,
        value: 34232
      };
    }

    return {
      expectedResult: getExpectedResult(unit),
      unit,
      value: 324234232.34233
    };
  });

  const rawTestCases = units.map((unit) => {
    if (unit === '%') {
      return {
        expectedResult: '45.55678%',
        unit,
        value: 45.55678
      };
    }

    if (unit === 'ms') {
      return {
        expectedResult: '34232 ms',
        unit,
        value: 34232
      };
    }

    return {
      expectedResult:
        unit === '' ? '324234232.34233 ' : `324234232.34233 ${unit}`,
      unit,
      value: 324234232.34233
    };
  });

  describe('Format the value as human readable', () => {
    it.each(humanReadableTestCases)(
      'formats the value with $unit',
      ({ value, unit, expectedResult }) => {
        expect(
          timeSeries.formatMetricValueWithUnit({
            unit,
            value
          })
        ).toEqual(expectedResult);
      }
    );
  });

  describe('Format the value as raw', () => {
    it.each(rawTestCases)(
      'formats the value with $unit',
      ({ value, unit, expectedResult }) => {
        expect(
          timeSeries.formatMetricValueWithUnit({
            isRaw: true,
            unit,
            value
          })
        ).toEqual(expectedResult);
      }
    );
  });
});
