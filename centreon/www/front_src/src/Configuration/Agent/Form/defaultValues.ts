import { find, propEq } from 'ramda';
import { connectionModes } from '../utils';

const defaultValues = {
  name: '',
  type: null,
  pollers: [],
  configuration: {},
  connectionMode: find(propEq('secure', 'id'), connectionModes)
};

export default defaultValues;
