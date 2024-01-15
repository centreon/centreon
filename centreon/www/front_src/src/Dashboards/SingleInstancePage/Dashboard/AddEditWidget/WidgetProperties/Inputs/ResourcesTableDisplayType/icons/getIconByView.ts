import { equals } from 'ramda';

import viewByService from './view_service.svg';
import viewByHost from './view_host.svg';
import viewByAll from './view_all.svg';

export const getIconbyView = (view): string => {
  if (equals(view, 'service')) {
    return viewByService;
  }
  if (equals(view, 'host')) {
    return viewByHost;
  }

  return viewByAll;
};
