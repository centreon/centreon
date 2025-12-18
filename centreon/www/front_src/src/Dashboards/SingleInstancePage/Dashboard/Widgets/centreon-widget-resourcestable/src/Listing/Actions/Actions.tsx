import { Grid } from '@mui/material';

import { DisplayType as DisplayTypeEnum } from '../models';
import DisplayType from './DisplayType';
import ResourceActions from './ResourceActions';

interface Props {
  displayType: DisplayTypeEnum;
  hasMetaService: boolean;
  setPanelOptions: (panelOptions) => void;
  isOpenTicketEnabled: boolean;
}

const Actions = ({
  displayType,
  setPanelOptions,
  hasMetaService,
  isOpenTicketEnabled
}: Props): JSX.Element => {
  return (
    <Grid container>
      <Grid flex={1} item>
        <ResourceActions />
      </Grid>
      <Grid flex={1} item>
        <DisplayType
          displayType={displayType}
          hasMetaService={hasMetaService}
          isOpenTicketEnabled={isOpenTicketEnabled}
          setPanelOptions={setPanelOptions}
        />
      </Grid>
    </Grid>
  );
};

export default Actions;
