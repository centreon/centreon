import { length, lte } from 'ramda';

import { ResourceDetails } from '../../../Details/models';
import { Resource } from '../../../models';
import { Line } from '../models';

import { getDisplayAdditionalLinesCondition } from './AnomalyDetectionAdditionalLines';

export const displayAdditionalLines = ({
  lines,
  resource
}: {
  lines: Array<Line>;
  resource: Resource | ResourceDetails;
}): boolean => {
  const isLegendClicked = lte(length(lines), 1);

  const displayAdditionalLinesCondition =
    getDisplayAdditionalLinesCondition?.condition(resource) || false;

  return displayAdditionalLinesCondition && !isLegendClicked;
};
