import { useState } from 'react';

import Widget from '.';

interface Props {
  text?: string;
}

// Controlled like in a dashboard: the panel options follow the input.
export const Default = ({ text = 'hello' }: Props): JSX.Element => {
  const [panelOptions, setPanelOptions] = useState({ text });

  return (
    <Widget panelOptions={panelOptions} setPanelOptions={setPanelOptions} />
  );
};
