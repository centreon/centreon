import { ListItem } from '@mui/material';

import { CollapsibleItem } from '@centreon/ui/components';

interface Props {
  singleHostPerMetric?: boolean;
  singleMetricSelection?: boolean;
}

export const useRenderOptions = () => {
  const renderOptionsForSingleMetric = (_, option): JSX.Element => {
    console.log(option);

    return (
      <ListItem>
        <CollapsibleItem title={`${option.name} (${option.unit})`}>
          coucou
        </CollapsibleItem>
      </ListItem>
    );
  };

  return {
    renderOptionsForSingleMetric
  };
};
