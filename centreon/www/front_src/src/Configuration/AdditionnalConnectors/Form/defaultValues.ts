import { getDefaultParameters } from '../utils';

export const defaultValues = {
  description: null,
  name: '',
  parameters: { port: 5700, vcenters: [getDefaultParameters(0)] },
  pollers: [],
  type: 1
};
