import { defaultParameters } from '../utils';

export const defaultValues = {
  description: null,
  name: '',
  parameters: { port: 5700, vcenters: [defaultParameters] },
  pollers: [],
  type: 1
};
