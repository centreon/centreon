import type { ComponentType } from 'react';

import { Gauge } from '../Gauge/Gauge';
import dataLastWeek from '../mockedData/lastWeek.json';
import SingleBar from '../SingleBar/SingleBar';
import { Text } from '../Text/Text';
import {
  criticalThresholds,
  rangedThresholds,
  successThresholds,
  warningThresholds
} from './testUtils';

const thresholdsByLevel = {
  critical: criticalThresholds,
  ranged: rangedThresholds,
  success: successThresholds,
  warning: warningThresholds
};

const dataSets = {
  empty: { global: {}, metrics: [], times: [] },
  lastWeek: dataLastWeek,
  none: undefined
};

interface Props {
  data?: keyof typeof dataSets;
  thresholds: keyof typeof thresholdsByLevel;
  [prop: string]: unknown;
}

// The graphs that color a value after its thresholds, on the same data.
const story =
  (Graph: ComponentType<Record<string, unknown>>) =>
  ({ data = 'lastWeek', thresholds, ...props }: Props): JSX.Element => (
    <div style={{ height: '100vh', width: '100vw' }}>
      <Graph
        baseColor="#000"
        data={dataSets[data]}
        labels={{ critical: 'Critical', warning: 'Warning' }}
        thresholds={thresholdsByLevel[thresholds]}
        {...props}
      />
    </div>
  );

// Their props differ in the options the specs leave out.
export const TextGraph = story(Text as never);
export const GaugeGraph = story(Gauge as never);
export const SingleBarGraph = story(SingleBar as never);
