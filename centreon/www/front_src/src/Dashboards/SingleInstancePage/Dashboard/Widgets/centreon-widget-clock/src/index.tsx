import { equals } from 'ramda';

import { CommonWidgetProps } from '../../models';
import Clock from './Clock';
import { ForceDimension, PanelOptions } from './models';
import Timer from './Timer';

interface Props extends CommonWidgetProps<PanelOptions> {
  panelOptions: PanelOptions;
}

const Widget = ({
  panelOptions,
  hasDescription,
  forceHeight,
  forceWidth
}: Props & ForceDimension): JSX.Element =>
  equals(panelOptions.displayType, 'clock') ? (
    <Clock
      {...panelOptions}
      forceHeight={forceHeight}
      forceWidth={forceWidth}
      hasDescription={hasDescription}
    />
  ) : (
    <Timer
      {...panelOptions}
      forceHeight={forceHeight}
      forceWidth={forceWidth}
      hasDescription={hasDescription}
    />
  );

export default Widget;
