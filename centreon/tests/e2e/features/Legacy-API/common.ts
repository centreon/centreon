interface RealtimeHost {
  id: number | string;
  name: string;
}

interface RealtimeService {
  description: string;
  host_id: number | string;
  name: string;
  output: string;
  perfdata: string;
  service_id: number | string;
  state: number | string;
}

interface MetricCurve {
  data: Array<number | null>;
  label: string;
}

interface MetricEntry {
  data: Array<MetricCurve>;
  service_id: string;
  times: Array<number | string>;
}

const contactPassword = 'Centreon!2021RbtApiV1';

// API v1 rights a contact can be given, by their label in the contact form
const apiRightParameters: Record<string, string> = {
  'Reach API Configuration': 'reach_api',
  'Reach API Realtime': 'reach_api_rt'
};

// Some API v1 errors are sent as a JSON string holding the JSON payload
const parseApiBody = (body: unknown): unknown => {
  if (typeof body !== 'string') {
    return body;
  }

  try {
    return JSON.parse(body);
  } catch {
    return body;
  }
};

const nowInSeconds = (): number => Math.floor(Date.now() / 1000);

const isNumericValue = (value: unknown): boolean =>
  typeof value === 'number' && !Number.isNaN(value);

const curveHasNumericValue = (curve: MetricCurve): boolean =>
  curve.data.some(isNumericValue);

const entryHasNumericValue = (entry: MetricEntry): boolean =>
  entry.data.some(curveHasNumericValue);

const buildHostQuery = (hostName: string, template: string): string =>
  `${hostName};${hostName};127.0.0.1;${template};central;`;

// Longest start shared by all the names, used as a search filter
const commonPrefix = (names: Array<string>): string =>
  names.reduce((prefix, name) => {
    let length = 0;

    while (length < prefix.length && prefix[length] === name[length]) {
      length += 1;
    }

    return prefix.slice(0, length);
  }, names[0] ?? '');

export type { MetricEntry, RealtimeHost, RealtimeService };
export {
  apiRightParameters,
  buildHostQuery,
  commonPrefix,
  contactPassword,
  curveHasNumericValue,
  entryHasNumericValue,
  isNumericValue,
  nowInSeconds,
  parseApiBody
};
