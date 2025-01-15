import { WidgetButtonGroup } from '..';
import { label12Hours, label24Hours } from '../../../../translatedLabels';
import { WidgetPropertyProps } from '../../../models';

import { useTimeFormat } from './useTimeFormat';

const options = [
  {
    id: '12',
    name: label12Hours
  },
  {
    id: '24',
    name: label24Hours
  }
];

const TimeFormat = ({
  propertyName,
  ...props
}: WidgetPropertyProps): JSX.Element => {
  useTimeFormat({ propertyName });

  return (
    <WidgetButtonGroup
      propertyName={propertyName}
      {...props}
      options={options}
    />
  );
};

export default TimeFormat;
